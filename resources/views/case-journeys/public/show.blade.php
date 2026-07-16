@php
    use Illuminate\Support\Str;

    $j          = $dto['journey'] ?? [];
    $nodes      = $dto['nodes'] ?? [];
    $root       = $nodes[0] ?? null;
    $plan       = $dto['plan'] ?? null;
    $planItems  = $plan['items'] ?? [];
    $planTotal  = $plan['total'] ?? ($estimate ?? 0);

    $costVis    = $j['cost_visibility'] ?? 'full';
    $showPrice  = $costVis !== 'hidden_until_booking';
    $isClosed   = in_array($journey->status, ['accepted', 'declined'], true);
    $preview    = $preview ?? false;   // doctor's pre-send preview — no real actions
    $firstName  = $j['patient_first_name'] ?? null;
    $patientName = $journey->patient?->name;
    $toothName  = $j['tooth_name'] ?? null;
    $toothCount = $j['tooth_count'] ?? null;
    $toothChip  = $toothCount && $toothCount > 1 ? ($toothCount . ' teeth') : $toothName;
    $clinic     = \App\Models\AppSetting::get('clinic_name', 'Your Clinic');
    $doctor     = $journey->treatmentPlan?->doctor?->name ?? $journey->creator?->name;

    $optionNodes = collect($root['children'] ?? [])->where('node_type', 'option')->values();
    $planTreatmentIds = collect($planItems)->pluck('treatment_id')->filter()->all();

    // "From" price of an option node: its default priced option, else base.
    $priceOf = function (array $node): ?float {
        $p = $node['pricing'] ?? null;
        if (! $p) return null;
        foreach ($p['options'] as $o) if (! empty($o['is_default'])) return (float) $o['price'];
        return isset($p['options'][0]) ? (float) $p['options'][0]['price'] : (float) $p['base_price'];
    };

    // Build the interactive options list; mark the dentist's recommendation.
    $optionsJs = [];
    $recommendedOptionId = null;
    foreach ($optionNodes as $i => $opt) {
        $matChild = collect($opt['children'] ?? [])->first(fn ($c) => ! empty($c['pricing']['options']));
        $intro = collect($opt['education']['blocks'] ?? [])->firstWhere('block_type', 'intro');
        $isRec = ($opt['is_recommended'] ?? false) || in_array($opt['treatment_id'] ?? null, $planTreatmentIds, true);
        if ($isRec && ! $recommendedOptionId) $recommendedOptionId = (string) $opt['id'];
        $optionsJs[] = [
            'id'           => (string) $opt['id'],
            'label'        => $opt['label'],
            'recommended'  => $isRec,
            'isCustom'     => (bool) ($opt['is_custom'] ?? false),
            'hasEdu'       => ! empty($opt['education']['blocks'] ?? []),
            'fromPrice'    => $priceOf($opt),
            'teaser'       => $intro ? Str::limit(strip_tags($intro['body'] ?? ''), 88) : null,
            'materialNode' => $matChild['id'] ?? null,
            'materials'    => $matChild['pricing']['options'] ?? [],
        ];
    }
    // Fallback: first option is the recommended path if the dentist didn't tag one.
    if (! $recommendedOptionId && count($optionsJs)) {
        $recommendedOptionId = $optionsJs[0]['id'];
        $optionsJs[0]['recommended'] = true;
    }

    // Drawer (KB education) keyed by node id.
    $drawerData = [];
    foreach ($optionNodes as $n) {
        $drawerData[$n['id']] = [
            'label'       => $n['label'],
            'recommended' => in_array($n['id'], [$recommendedOptionId], true),
            'blocks'      => array_values($n['education']['blocks'] ?? []),
        ];
    }

    // Charted findings per tooth (if the plan's consultation has a chart).
    $conditionByTooth = [];
    $consult = $journey->treatmentPlan?->consultation;
    if ($consult && is_array($consult->chart_data ?? null)) {
        foreach ($consult->chart_data as $row) {
            if (is_array($row) && isset($row['tooth'])) {
                $conditionByTooth[(string) $row['tooth']] = $row['condition'] ?? ($row['custom'] ?? null);
            }
        }
    }

    // Per-tooth map for the interactive chart (from the real plan items).
    $toothMap = [];
    foreach ($planItems as $item) {
        $teeth = collect(preg_split('/[^0-9]+/', (string) $item['teeth']))->filter()->values();
        $per   = $teeth->count() ? round($item['total'] / $teeth->count()) : $item['total'];
        foreach ($teeth as $t) {
            $toothMap[(string) $t] = [
                'treatment' => $item['treatment_name'],
                'price'     => $per,
                'condition' => $conditionByTooth[(string) $t] ?? null,
            ];
        }
    }

    $upper = [18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28];
    $lower = [48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38];
    $condLabels = ['crown'=>'Crown','composite'=>'Filling','amalgam'=>'Filling','veneer'=>'Veneer','rct'=>'Root canal','implant'=>'Implant','mobile'=>'Loose tooth','missing'=>'Missing tooth','cavity'=>'Cavity'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Your Treatment Plan — {{ $clinic }}</title>
<style>
  :root{
    --ink:#241a26;--muted:#7a6b7e;--line:#efe4ee;--bg:#faf5f9;
    --brand:#6a0f70;--brand-dark:#4e0a53;--brand-soft:#f3e8f4;
    --accent:#8e24aa;--gold:#8e24aa;--good:#1f8a5b;--card:#fff;
    --radius:14px;--shadow:0 1px 3px rgba(80,20,60,.06),0 8px 24px rgba(80,20,60,.07);
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--ink);line-height:1.55;-webkit-font-smoothing:antialiased}
  .topbar{position:sticky;top:0;z-index:40;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-bottom:1px solid var(--line)}
  .topbar-in{max-width:1120px;margin:0 auto;padding:11px 18px;display:flex;align-items:center;justify-content:space-between}
  .clinic{display:flex;align-items:center;gap:10px;font-weight:700;font-size:15px}
  .logo{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--brand),var(--brand-dark));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800}
  .secure{font-size:11.5px;color:var(--muted);display:flex;align-items:center;gap:5px}
  .secure::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--good)}
  .shell{max-width:1120px;margin:0 auto;padding:0 18px 40px;display:grid;grid-template-columns:1fr 330px;gap:24px;align-items:start}
  .main{min-width:0}.aside{position:sticky;top:70px}
  .progress{padding:14px 0 4px}.steps{display:flex;gap:6px}.step{flex:1;text-align:center}
  .step .bar{height:5px;border-radius:99px;background:var(--line);transition:.4s}
  .step.done .bar,.step.active .bar{background:var(--brand)}
  .step .lbl{font-size:11px;color:var(--muted);margin-top:6px;font-weight:600}
  .step.active .lbl,.step.done .lbl{color:var(--brand)}
  section{margin-top:18px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:22px}
  .eyebrow{font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--brand)}
  h1{font-size:25px;line-height:1.25;margin:6px 0 8px;letter-spacing:-.01em}
  h2{font-size:20px;margin:0 0 4px;letter-spacing:-.01em}
  p.lead{color:var(--muted);font-size:15px}
  .prose{font-size:14.5px;color:#3a2b3a;margin-top:6px}
  .hero{background:linear-gradient(160deg,#8e24aa 0%,#4e0a53 100%);color:#fff;border:none;position:relative;overflow:hidden}
  .hero h1{color:#fff}.hero p{color:#f0e2f6;font-size:15px;max-width:60ch}
  .hero .hero-greet{font-size:15px;font-weight:600;color:#f0e2f6;margin-bottom:2px}
  .hero .meta{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}
  .chip{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);padding:6px 12px;border-radius:99px;font-size:12.5px;font-weight:600}
  /* Chart */
  .arch{margin-top:16px;background:linear-gradient(180deg,#fbf6fb,#f5ecf5);border:1px solid var(--line);border-radius:14px;padding:18px 12px}
  .arch-cap{text-align:center;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin:2px 0 8px}
  .tooth-row{display:flex;justify-content:center;gap:3px;flex-wrap:nowrap;overflow-x:auto;padding:4px 2px}
  .tooth-mid{width:2px;background:var(--line);border-radius:2px;margin:0 4px}
  .tooth{flex:0 0 auto;min-width:26px;height:34px;border-radius:7px;border:1.5px solid var(--line);background:#fff;color:#b7abbb;font-size:10.5px;font-weight:700;cursor:default;transition:.15s;display:flex;align-items:center;justify-content:center}
  .tooth.has{background:var(--brand);border-color:var(--brand);color:#fff;cursor:pointer;box-shadow:0 4px 10px rgba(106,15,112,.25)}
  .tooth.has:hover{transform:translateY(-2px)}
  .arch-hint{text-align:center;font-size:12.5px;color:var(--muted);margin-top:10px}.arch-hint b{color:var(--brand)}
  /* Anatomical odontogram */
  .odgram{max-width:640px;margin:0 auto}
  .od-row{display:flex;justify-content:center;align-items:flex-end;gap:2px;overflow-x:auto;padding:0 2px}
  .od-cell{flex:0 0 auto}
  .od-tooth{width:27px;height:58px;display:block;filter:drop-shadow(0 1px 1px rgba(80,40,20,.15))}
  .od-tooth path:not(.od-shine){fill:url(#odIvory);stroke:#cdb98d;stroke-width:.9;transition:.15s}
  .od-shine{fill:url(#odShine);stroke:none;pointer-events:none}
  .od-tooth.flip{transform:scaleY(-1)}
  .od-tooth.on path:not(.od-shine){fill:url(#odBrand);stroke:var(--brand-dark)}
  .od-tooth.on{cursor:pointer}
  .od-tooth.on:hover{filter:drop-shadow(0 2px 4px rgba(106,15,112,.4))}
  .od-mid{width:8px;flex:0 0 auto}
  .od-nrow{display:flex;justify-content:center;gap:2px;padding:4px 2px}
  .od-num{width:26px;text-align:center;font-size:9.5px;font-weight:700;color:#b7abbb;flex:0 0 auto}
  .od-num.on{color:var(--brand)}
  .od-mid-n{width:8px;flex:0 0 auto}
  .od-split{height:1px;background:var(--line);margin:2px 8px}
  /* Options / step chooser */
  .step-h{display:flex;align-items:baseline;gap:8px}
  .step-num{flex:none;width:22px;height:22px;border-radius:50%;background:var(--brand);color:#fff;font-size:12px;font-weight:800;display:inline-flex;align-items:center;justify-content:center}
  .opt-grid{display:grid;gap:10px;margin-top:14px}
  .opt-card{position:relative;border:2px solid var(--line);border-radius:12px;padding:14px 16px;cursor:pointer;transition:.15s;background:#fff}
  .opt-card:hover{border-color:#d9bfe0}
  .opt-card.sel{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}
  .opt-rec{position:absolute;top:-10px;left:14px;background:var(--brand);color:#fff;font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;padding:3px 9px;border-radius:99px}
  .opt-top{display:flex;align-items:center;gap:10px}
  .opt-radio{flex:none;width:20px;height:20px;border-radius:50%;border:2px solid #cdbcd3;position:relative;transition:.15s}
  .opt-card.sel .opt-radio{border-color:var(--brand)}
  .opt-card.sel .opt-radio::after{content:"";position:absolute;inset:3px;border-radius:50%;background:var(--brand)}
  .opt-name{font-weight:800;font-size:16px}
  .opt-from{margin-left:auto;font-weight:800;font-size:15px;white-space:nowrap}
  .opt-from small{font-weight:600;font-size:11px;color:var(--muted)}
  .opt-teaser{font-size:13px;color:var(--muted);margin:6px 0 0 30px}
  .opt-learn{margin:8px 0 0 30px;background:none;border:none;color:var(--brand);font-weight:700;font-size:12.5px;cursor:pointer;padding:0}
  .mat-step{margin-top:18px;border-top:1px dashed var(--line);padding-top:16px}
  .mat-step[hidden]{display:none}
  .mat-grid{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
  .mat-card{flex:1;min-width:120px;border:2px solid var(--line);border-radius:10px;padding:12px;cursor:pointer;transition:.15s;text-align:center}
  .mat-card.sel{border-color:var(--brand);background:var(--brand-soft)}
  .mat-name{font-weight:700;font-size:14px}.mat-price{font-size:13px;color:var(--brand);font-weight:700;margin-top:3px}
  .faq details{border-bottom:1px solid var(--line);padding:12px 0}
  .faq summary{cursor:pointer;font-weight:600;font-size:14.5px;list-style:none;display:flex;justify-content:space-between}
  .faq summary::-webkit-details-marker{display:none}.faq summary::after{content:"+";color:var(--brand);font-weight:800}
  .faq details[open] summary::after{content:"–"}.faq details p{color:var(--muted);font-size:14px;margin-top:8px}
  .footnote{text-align:center;color:var(--muted);font-size:11.5px;margin-top:24px}
  .about-grid{display:grid;grid-template-columns:56px 1fr;gap:16px;margin-top:12px}
  .about-logo{width:56px;height:56px;border-radius:12px;background:linear-gradient(135deg,var(--brand),var(--brand-dark));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:24px}
  /* Drawer + popup */
  .scrim{position:fixed;inset:0;background:rgba(40,10,50,.5);opacity:0;visibility:hidden;transition:.25s;z-index:70}.scrim.show{opacity:1;visibility:visible}
  .drawer{position:fixed;top:0;right:0;bottom:0;width:440px;max-width:92vw;background:var(--bg);z-index:71;transform:translateX(100%);transition:transform .32s cubic-bezier(.2,.8,.2,1);display:flex;flex-direction:column;box-shadow:-12px 0 40px rgba(40,10,50,.18)}
  .drawer.open{transform:none}
  .drawer-top{display:flex;align-items:center;gap:10px;padding:14px 16px;background:#fff;border-bottom:1px solid var(--line)}
  .drawer-top .dt-name{font-weight:800;font-size:16px}
  .drawer-close{margin-left:auto;background:none;border:none;font-size:20px;color:var(--muted);cursor:pointer}
  .drawer-body{overflow:auto;padding:16px;flex:1}
  .kb-hero{background:linear-gradient(160deg,#8e24aa,#4e0a53);color:#fff;border-radius:12px;padding:16px}
  .kb-hero .rectag{display:inline-block;background:rgba(255,255,255,.2);color:#fff;font-size:10.5px;font-weight:800;text-transform:uppercase;padding:3px 9px;border-radius:99px;margin-bottom:8px}
  .kb-hero h3{font-size:19px}
  .kb-h{font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--brand);margin:16px 0 6px}
  .kb-body{font-size:13.5px;color:#3a2b3a}
  .tp-scrim{position:fixed;inset:0;background:rgba(40,10,50,.5);display:none;align-items:center;justify-content:center;z-index:80;padding:20px}.tp-scrim.show{display:flex}
  .tpop{background:#fff;border-radius:16px;max-width:340px;width:100%;padding:22px;box-shadow:var(--shadow)}
  .tp-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
  .tp-tooth{font-weight:800;font-size:18px}.tp-x{background:none;border:none;font-size:18px;color:var(--muted);cursor:pointer}
  .tp-cond{display:inline-block;background:#fdeef0;color:#b23a58;font-size:11.5px;font-weight:700;padding:3px 9px;border-radius:99px;margin-bottom:8px}
  .tp-tx{font-weight:700;font-size:16px;color:var(--brand)}.tp-price{font-size:15px;color:var(--ink);margin-top:2px}
  /* Cart */
  .cart{background:#fff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}
  .cart-head{background:var(--brand);color:#fff;padding:14px 16px;font-weight:800;font-size:15px}
  .cart-body{padding:14px 16px}
  .cline{display:flex;align-items:flex-start;gap:8px;padding:10px 0;border-bottom:1px dashed var(--line)}
  .cline .ln{font-weight:700;font-size:13.5px}.cline .ld{font-size:11.5px;color:var(--muted)}
  .cline .lp{margin-left:auto;font-weight:700;font-size:13.5px;white-space:nowrap}
  .totrow{display:flex;align-items:baseline;justify-content:space-between;padding:14px 0 4px}
  .totrow .tl{font-size:13px;color:var(--muted);font-weight:600}.totrow .tv{font-size:26px;font-weight:800;letter-spacing:-.02em;color:var(--brand)}
  .frame{font-size:11.5px;color:#5c4a66;background:var(--brand-soft);border:1px solid #e4d3ea;border-radius:8px;padding:8px 10px;margin-top:10px;display:flex;gap:7px}
  .cta{display:grid;gap:8px;margin-top:14px}
  .btn{border:none;border-radius:10px;padding:12px 14px;font-weight:700;font-size:14px;cursor:pointer;transition:.15s;width:100%;font-family:inherit}
  .btn-primary{background:var(--brand);color:#fff}.btn-primary:hover{background:var(--brand-dark)}
  .btn-row{display:flex;gap:8px}.btn-ghost{background:#fff;color:var(--ink);border:1px solid var(--line)}
  .closed-banner{background:var(--brand-soft);border:1px solid #e4d3ea;color:var(--brand-dark);border-radius:12px;padding:14px 16px;text-align:center;font-size:14px;margin-top:16px}
  @media(max-width:820px){.shell{grid-template-columns:1fr}.aside{position:static}}
  @media (prefers-reduced-motion:no-preference){
    .reveal{opacity:0;transform:translateY(16px);transition:opacity .55s ease,transform .55s cubic-bezier(.2,.8,.2,1)}.reveal.in{opacity:1;transform:none}
    .tooth.has{animation:tglow 2.6s ease-in-out infinite}@keyframes tglow{0%,100%{box-shadow:0 4px 10px rgba(106,15,112,.25)}50%{box-shadow:0 4px 16px rgba(106,15,112,.5)}}
  }
</style>
</head>
<body>
<div class="topbar"><div class="topbar-in">
  <div class="clinic"><span class="logo">{{ Str::substr($clinic, 0, 1) }}</span> {{ $clinic }}</div>
  <div class="secure">Private &amp; secure link</div>
</div></div>

<div class="shell">
  <div class="main">
    <div class="progress"><div class="steps">
      <div class="step done"><div class="bar"></div><div class="lbl">Your teeth</div></div>
      <div class="step active" id="pstep2"><div class="bar"></div><div class="lbl">Choose care</div></div>
      <div class="step" id="pstep3"><div class="bar"></div><div class="lbl">Your decision</div></div>
    </div></div>

    <section>
      <div class="card hero">
        @if ($patientName)<div class="hero-greet">Hi, {{ $patientName }} 👋</div>@endif
        <h1>Here’s your treatment plan</h1>
        <p>Take a look at what your dentist found, explore your options, and choose what feels right for you.</p>
        <div class="meta">
          @if($doctor)<span class="chip">Prepared by Dr. {{ Str::of($doctor)->replace('Dr. ', '') }}</span>@endif
          @if($toothChip)<span class="chip">🦷 {{ $toothChip }}</span>@endif
          <span class="chip">⏱ 2 min</span>
        </div>
      </div>
    </section>

    @if ($isClosed)
      <div class="closed-banner">
        @if ($journey->status === 'accepted')
          Thank you{{ $patientName ? ', '.$patientName : '' }} — your decision has been shared with the clinic. We’ll be in touch to plan the next steps.
        @else
          Thanks for reviewing this. If you change your mind, just contact the clinic.
        @endif
      </div>
    @endif

    {{-- Tooth chart --}}
    @if (!empty($toothMap))
      <section>
        <div class="card">
          <div class="eyebrow">Your teeth</div>
          <h2>Where you need care</h2>
          <p class="lead">The teeth in purple are the ones your dentist is treating. Tap any to see what’s going on.</p>
          <div class="arch">
            @include('case-journeys.public.partials.odontogram', ['upper' => $upper, 'lower' => $lower, 'toothMap' => $toothMap])
          </div>
          <p class="arch-hint">👆 <b>Tap a purple tooth</b> to see what’s planned for it.</p>
        </div>
      </section>
    @endif

    {{-- Condition --}}
    @if ($root && !empty($root['education']))
      <section>
        <div class="card">
          <div class="eyebrow">Why it matters</div>
          <h2>{{ $root['education']['topic']['title'] ?? 'Your condition' }}</h2>
          @foreach ($root['education']['blocks'] as $b)
            @if (!empty($b['title']))<div class="eyebrow" style="color:var(--muted);margin-top:10px">{{ $b['title'] }}</div>@endif
            <p class="prose">{{ $b['body'] }}</p>
          @endforeach
        </div>
      </section>
    @endif

    {{-- Guided step-by-step chooser --}}
    @if (!empty($optionsJs))
      <section>
        <div class="card">
          <div class="step-h"><span class="step-num">1</span><h2>Choose how to treat it</h2></div>
          <p class="lead" style="margin-top:6px">Your dentist recommends the highlighted option — but it’s your choice. Tap to compare.</p>

          <div class="opt-grid">
            @foreach ($optionsJs as $opt)
              <div class="opt-card" data-id="{{ $opt['id'] }}" onclick="CJ.selectOption('{{ $opt['id'] }}')">
                @if ($opt['recommended'])<span class="opt-rec">Dentist’s recommendation</span>@endif
                <div class="opt-top">
                  <span class="opt-radio"></span>
                  <span class="opt-name">{{ $opt['label'] }}</span>
                  @if ($showPrice && $opt['fromPrice'])<span class="opt-from">₹{{ number_format($opt['fromPrice']) }}<small> from</small></span>@endif
                </div>
                @if ($opt['teaser'])<div class="opt-teaser">{{ $opt['teaser'] }}</div>@endif
                @if ($opt['hasEdu'])<button class="opt-learn" onclick="event.stopPropagation();CJ.more('{{ $opt['id'] }}')">Learn about this →</button>@endif
              </div>
            @endforeach
          </div>

          {{-- Material step (revealed for options that have a finish choice) --}}
          <div class="mat-step" id="matStep" hidden>
            <div class="step-h"><span class="step-num">2</span><h2 style="font-size:17px">Choose the finish</h2></div>
            <p class="lead" style="margin-top:6px" id="matHint">Pick the crown material for your new tooth.</p>
            <div class="mat-grid" id="matCards"></div>
          </div>
        </div>
      </section>
    @endif

    {{-- FAQ --}}
    @php
        $faqs = collect($nodes)->flatMap(function ($n) {
            return collect($n['education']['blocks'] ?? [])->where('block_type', 'faq')
                ->merge(collect($n['children'] ?? [])->flatMap(fn ($c) => collect($c['education']['blocks'] ?? [])->where('block_type', 'faq')));
        });
    @endphp
    @if ($faqs->isNotEmpty())
      <section>
        <div class="card faq">
          <div class="eyebrow">Common questions</div>
          <h2 style="margin-bottom:8px">Quick answers</h2>
          @foreach ($faqs as $f)<details><summary>{{ $f['title'] ?? 'More info' }}</summary><p>{{ $f['body'] }}</p></details>@endforeach
        </div>
      </section>
    @endif

    <section>
      <div class="card">
        <div class="eyebrow">About us</div>
        <div class="about-grid">
          <div class="about-logo">{{ Str::substr($clinic, 0, 1) }}</div>
          <div>
            <h2>{{ $clinic }}</h2>
            @if ($doctor)<p class="lead" style="margin-top:2px">Your care team · Dr. {{ Str::of($doctor)->replace('Dr. ', '') }}</p>@endif
          </div>
        </div>
      </div>
    </section>

    <p class="footnote">Private to this secure link · prices are current estimates 🔒</p>
  </div>

  {{-- Decision --}}
  <aside class="aside">
    <div class="cart">
      <div class="cart-head">Your choice</div>
      <div class="cart-body">
        <div id="cartSummary"><p class="lead" style="font-size:13.5px">Pick an option to see your estimate.</p></div>
        @if ($showPrice)
          <div class="totrow" id="totRow" style="display:none"><span class="tl">{{ $costVis === 'starting_from' ? 'Estimated from' : 'Your estimate' }}</span><span class="tv" id="cartTotal">₹0</span></div>
        @endif
        <div class="frame"><span>ℹ️</span><span><b>Estimate only.</b> Your dentist confirms the final treatment &amp; cost in person.</span></div>

        @if ($preview)
          <div class="cta"><div style="text-align:center;font-size:12px;color:var(--brand);background:var(--brand-soft);border-radius:9px;padding:10px;font-weight:600">👁 Preview — the patient will see <b>Go ahead / Call me / Not now</b> here.</div></div>
        @elseif (! $isClosed)
          <div class="cta">
            <div id="ctaAccept">
              <form method="POST" action="{{ route('case.public.accept', $token) }}">@csrf
                <button class="btn btn-primary">Go ahead with this plan</button>
              </form>
            </div>
            <div id="ctaDiscuss" style="display:none">
              <form method="POST" action="{{ route('case.public.request-callback', $token) }}">@csrf
                <button class="btn btn-primary">I’d like this — talk to my dentist</button>
              </form>
            </div>
            <div class="btn-row">
              <form method="POST" action="{{ route('case.public.request-callback', $token) }}" style="flex:1">@csrf
                <button class="btn btn-ghost">Call me</button>
              </form>
              <form method="POST" action="{{ route('case.public.decline', $token) }}" style="flex:1">@csrf
                <button class="btn btn-ghost" style="color:var(--muted)">Not now</button>
              </form>
            </div>
          </div>
        @endif
      </div>
    </div>
  </aside>
</div>

{{-- Tooth popup --}}
<div class="tp-scrim" id="tpScrim" onclick="CJ.closeTooth()">
  <div class="tpop" onclick="event.stopPropagation()">
    <div class="tp-head"><span class="tp-tooth" id="tpTooth">Tooth</span><button class="tp-x" onclick="CJ.closeTooth()">✕</button></div>
    <div id="tpBody"></div>
  </div>
</div>

{{-- Know-more drawer --}}
<div class="scrim" id="scrim" onclick="CJ.closeDrawer()"></div>
<aside class="drawer" id="drawer" aria-hidden="true">
  <div class="drawer-top"><span class="dt-name" id="drawerName">Treatment</span><button class="drawer-close" onclick="CJ.closeDrawer()">✕</button></div>
  <div class="drawer-body" id="drawerBody"></div>
</aside>

<script>
const CJ = (function () {
  const OPTIONS = @json(array_values($optionsJs));
  const OPT = {}; OPTIONS.forEach(o => OPT[o.id] = o);
  const REC = @json($recommendedOptionId);
  const DRAWER = @json($drawerData);
  const TEETH = @json($toothMap);
  const COND  = @json($condLabels);
  const SHOW_PRICE = @json($showPrice);
  const CLOSED = @json($isClosed);
  const TOKEN = @json($token);
  const CSRF = document.querySelector('meta[name=csrf-token]').content;
  const el = id => document.getElementById(id);
  const rupee = n => '₹' + Number(n||0).toLocaleString('en-IN');
  let selOpt = null, optBase = 0, selMatId = null, selMatPrice = 0, selMatName = null;

  function setStep(n){ el('pstep2') && el('pstep2').classList.toggle('done', n>2); el('pstep3') && el('pstep3').classList.toggle('active', n>=3); }

  function record(nodeId, optId){
    if(!TOKEN) return;   // preview mode — nothing to record
    fetch(`/p/${TOKEN}/select`, {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
      body:JSON.stringify({node_id:nodeId, option_id:optId})}).catch(()=>{});
  }

  function selectOption(id){
    if(CLOSED) return;
    selOpt = String(id); const o = OPT[selOpt]; if(!o) return; optBase = +o.fromPrice || 0;
    document.querySelectorAll('.opt-card').forEach(c => c.classList.toggle('sel', c.dataset.id === selOpt));
    if(o.materials && o.materials.length){
      el('matCards').innerHTML = o.materials.map(m =>
        `<div class="mat-card" data-opt="${m.id}" onclick="CJ.selectMaterial(${o.materialNode}, ${m.id})"><div class="mat-name">${m.name}</div>${SHOW_PRICE?`<div class="mat-price">+ ${rupee(m.price)}</div>`:''}</div>`).join('');
      el('matStep').hidden = false;
      const def = o.materials.find(m => m.is_default) || o.materials[0];
      selectMaterial(o.materialNode, def.id, true);
    } else {
      el('matStep').hidden = true; selMatId = null; selMatPrice = 0; selMatName = null;
      render();
    }
    if(selOpt.charAt(0) !== 't') record(selOpt, null);   // custom options have no node to record against
  }

  function selectMaterial(nodeId, optId, silent){
    const o = OPT[selOpt]; const m = (o.materials||[]).find(x => x.id === optId); if(!m) return;
    selMatId = optId; selMatPrice = +m.price || 0; selMatName = m.name;
    document.querySelectorAll('.mat-card').forEach(c => c.classList.toggle('sel', +c.dataset.opt === optId));
    if(!silent) record(nodeId, optId);
    render();
  }

  function render(){
    const o = OPT[selOpt]; if(!o) return;
    let h = `<div class="cline"><div><div class="ln">${o.label}</div><div class="ld">Treatment</div></div>${SHOW_PRICE?`<div class="lp">${rupee(optBase)}</div>`:''}</div>`;
    if(selMatName) h += `<div class="cline"><div><div class="ln">${selMatName}</div><div class="ld">Finish</div></div>${SHOW_PRICE?`<div class="lp">+ ${rupee(selMatPrice)}</div>`:''}</div>`;
    el('cartSummary').innerHTML = h;
    if(SHOW_PRICE && el('totRow')){ el('totRow').style.display='flex'; el('cartTotal').textContent = rupee(optBase + selMatPrice); }
    const aligned = (selOpt === REC);
    if(el('ctaAccept'))  el('ctaAccept').style.display  = aligned ? '' : 'none';
    if(el('ctaDiscuss')) el('ctaDiscuss').style.display = aligned ? 'none' : '';
    setStep(3);
  }

  function more(id){
    const d = DRAWER[id]; if(!d) return;
    el('drawerName').textContent = d.label;
    let h = `<div class="kb-hero">${d.recommended?'<span class="rectag">Dentist’s recommendation</span>':''}<h3>${d.label}</h3></div>`;
    (d.blocks||[]).forEach(b=>{ if(b.title) h+=`<div class="kb-h">${b.title}</div>`; if(b.body) h+=`<p class="kb-body">${b.body}</p>`; });
    el('drawerBody').innerHTML = h; el('drawerBody').scrollTop = 0;
    el('scrim').classList.add('show'); el('drawer').classList.add('open');
  }
  function closeDrawer(){ el('scrim').classList.remove('show'); el('drawer').classList.remove('open'); }

  function tooth(t){
    const d = TEETH[t]; if(!d) return;
    el('tpTooth').textContent = 'Tooth ' + t;
    let h = '';
    if(d.condition){ h += `<div class="tp-cond">Finding: ${COND[d.condition] || d.condition}</div>`; }
    h += `<div class="tp-tx">${d.treatment}</div>`;
    if(SHOW_PRICE && d.price){ h += `<div class="tp-price">Approx. ${rupee(d.price)} for this tooth</div>`; }
    el('tpBody').innerHTML = h; el('tpScrim').classList.add('show');
  }
  function closeTooth(){ el('tpScrim').classList.remove('show'); }

  document.addEventListener('DOMContentLoaded', ()=>{
    if(REC && !CLOSED) selectOption(REC);
    if('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion:reduce)').matches){
      const io = new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target);}}),{threshold:.12});
      document.querySelectorAll('.main section').forEach((c,i)=>{c.classList.add('reveal');c.style.transitionDelay=(i*40)+'ms';io.observe(c);});
    }
  });
  return { selectOption, selectMaterial, more, closeDrawer, tooth, closeTooth };
})();
</script>
</body>
</html>
