# Wake-word files go here

Place these two files (see `docs/tulip-wake-word-setup.md`):

- `Hey-Tulip.ppn`         — your generated Porcupine keyword (Web/WASM, English)
- `porcupine_params.pv`   — the Porcupine English model file

They are served at `/wake/Hey-Tulip.ppn` and `/wake/porcupine_params.pv`.
Without them, the wake word stays off; push-to-talk still works.
