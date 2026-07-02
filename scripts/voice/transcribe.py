#!/usr/bin/env python
"""
transcribe.py — local speech-to-text for Dentfluence (Tulip voice).
------------------------------------------------------------------------------
Uses faster-whisper to transcribe an audio file. Runs on the GPU (CUDA) with a
CPU fallback. Auto-detects language by default, so staff can dictate in English,
Hindi, Marathi, etc. Prints a single JSON line: {"text": "...", "language": ".."}

Usage:
    python transcribe.py <audio_file> [--model small] [--device cuda]
                         [--compute int8_float16] [--language auto]

Requires:  pip install faster-whisper
"""
import os
# Quiet the noisy Hugging Face warnings (symlinks, progress bars, token).
os.environ.setdefault("HF_HUB_DISABLE_SYMLINKS_WARNING", "1")
os.environ.setdefault("HF_HUB_DISABLE_PROGRESS_BARS", "1")
os.environ.setdefault("HF_HUB_DISABLE_TELEMETRY", "1")


def _add_cuda_dll_dirs():
    """The pip nvidia-cublas-cu12 / nvidia-cudnn-cu12 packages drop their DLLs
    inside site-packages\\nvidia\\<lib>\\bin, which Windows doesn't search by
    default — so CTranslate2 can't find cublas64_12.dll on the GPU. Add those
    folders to the DLL search path BEFORE faster-whisper (ctranslate2) loads."""
    if not hasattr(os, "add_dll_directory"):
        return
    try:
        import nvidia
        bases = list(getattr(nvidia, "__path__", []))
    except Exception:
        bases = []
    for base in bases:
        for sub in ("cublas", "cudnn", "cuda_runtime", "cuda_nvrtc"):
            d = os.path.join(base, sub, "bin")
            if os.path.isdir(d):
                try:
                    os.add_dll_directory(d)
                except Exception:
                    pass


_add_cuda_dll_dirs()

import sys
import json
import argparse


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("audio", help="Path to the audio file")
    ap.add_argument("--model", default="small")
    ap.add_argument("--device", default="cuda")
    ap.add_argument("--compute", default="int8_float16")
    ap.add_argument("--language", default="auto", help="Language code, or 'auto' to detect")
    args = ap.parse_args()

    try:
        from faster_whisper import WhisperModel
    except ImportError:
        print(json.dumps({"error": "faster-whisper is not installed. Run: pip install faster-whisper"}))
        sys.exit(0)

    language = None if args.language in ("auto", "", None) else args.language

    def run(device, compute, beam):
        """Load the model on a device and transcribe. Covers both load AND
        inference, so CUDA-library failures (e.g. missing cublas) are caught.
        Uses a smaller beam on CPU for speed (less search, still accurate enough
        for short dictation)."""
        model = WhisperModel(args.model, device=device, compute_type=compute)
        segments, info = model.transcribe(
            args.audio,
            language=language,
            vad_filter=True,   # skip silence
            beam_size=beam,
        )
        text = "".join(seg.text for seg in segments).strip()
        return text, getattr(info, "language", None)

    # Try the requested device (GPU), then fall back to CPU if CUDA libs are
    # missing or anything GPU-related fails. Beam: 5 on GPU, 1 on CPU (faster).
    try:
        text, lang = run(args.device, args.compute, 1 if args.device == "cpu" else 5)
    except Exception as e_gpu:
        if args.device != "cpu":
            try:
                text, lang = run("cpu", "int8", 1)
            except Exception as e_cpu:
                print(json.dumps({"error": f"Transcription failed on GPU and CPU: {e_cpu}"}))
                sys.exit(0)
        else:
            print(json.dumps({"error": f"Transcription failed: {e_gpu}"}))
            sys.exit(0)

    print(json.dumps({"text": text, "language": lang}))


if __name__ == "__main__":
    main()
