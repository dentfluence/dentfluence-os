<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'HQ') · Dentfluence HQ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,600&family=Spline+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --accent:#a01a86; --accent-soft:#f6e4f2; --ink:#2b2135; --ink-soft:#6d6178;
  --paper:#faf7fb; --card:#ffffff; --line:#e9e0ee;
  --ok:#1a7a4a; --warn:#b06a00; --bad:#b3261e;
}
*{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);font:15px/1.5 'Spline Sans',sans-serif}
a{color:var(--accent);text-decoration:none}
h1,h2{font-family:'Newsreader',serif;font-weight:600;margin:0}
h1{font-size:1.7rem} h2{font-size:1.15rem;margin-bottom:.6rem}
.shell{display:flex;min-height:100vh}
nav{width:210px;background:var(--ink);color:#efe8f3;padding:1.2rem .9rem;flex-shrink:0}
nav .brand{font-family:'Newsreader',serif;font-size:1.2rem;color:#fff;margin-bottom:1.4rem;display:block}
nav .brand span{color:#e58ad0}
nav a.item{display:block;color:#cfc3d8;padding:.45rem .6rem;border-radius:8px;margin-bottom:.15rem;font-size:.92rem}
nav a.item.active,nav a.item:hover{background:rgba(160,26,134,.35);color:#fff}
main{flex:1;padding:1.6rem 2rem;max-width:1100px}
.topbar{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:1.3rem}
.muted{color:var(--ink-soft);font-size:.86rem}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.9rem;margin-bottom:1.4rem}
.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:.9rem 1rem}
.card .num{font-family:'Newsreader',serif;font-size:1.9rem;font-weight:600}
.card .lbl{color:var(--ink-soft);font-size:.8rem;text-transform:uppercase;letter-spacing:.05em}
.panel{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:1.1rem 1.2rem;margin-bottom:1.2rem}
table{width:100%;border-collapse:collapse;font-size:.9rem}
th{text-align:left;color:var(--ink-soft);font-weight:500;font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;padding:.4rem .6rem;border-bottom:1px solid var(--line)}
td{padding:.55rem .6rem;border-bottom:1px solid var(--line);vertical-align:top}
tr:last-child td{border-bottom:0}
.pill{display:inline-block;padding:.1rem .55rem;border-radius:999px;font-size:.76rem;font-weight:500;background:var(--accent-soft);color:var(--accent)}
.pill.ok{background:#e2f3e9;color:var(--ok)} .pill.warn{background:#fbeedd;color:var(--warn)} .pill.bad{background:#fbe3e1;color:var(--bad)} .pill.dim{background:#eee9f1;color:var(--ink-soft)}
.btn{display:inline-block;background:var(--accent);color:#fff;border:0;border-radius:8px;padding:.45rem .9rem;font:inherit;font-size:.88rem;cursor:pointer}
.btn.ghost{background:transparent;color:var(--accent);border:1px solid var(--accent)}
input,select,textarea{font:inherit;padding:.42rem .55rem;border:1px solid var(--line);border-radius:8px;background:#fff;width:100%}
label{font-size:.8rem;color:var(--ink-soft);display:block;margin-bottom:.15rem}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:.8rem} .grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem}
details.adder{margin-bottom:1.2rem}
details.adder summary{cursor:pointer;color:var(--accent);font-weight:500;margin-bottom:.6rem}
.flash{background:#e2f3e9;color:var(--ok);border-radius:8px;padding:.5rem .9rem;margin-bottom:1rem;font-size:.9rem}
.filters a{margin-right:.7rem;font-size:.88rem}.filters a.on{font-weight:600;border-bottom:2px solid var(--accent)}
form.inline{display:inline}
</style>
</head>
<body>
<div class="shell">
  <nav>
    <a class="brand" href="{{ route('hq.dashboard') }}">dent<span>fluence</span> HQ</a>
    <a class="item {{ request()->routeIs('hq.dashboard') ? 'active' : '' }}" href="{{ route('hq.dashboard') }}">Dashboard</a>
    <a class="item {{ request()->routeIs('hq.clinics.*') ? 'active' : '' }}" href="{{ route('hq.clinics.index') }}">Clinics</a>
    <a class="item {{ request()->routeIs('hq.subscriptions.*') ? 'active' : '' }}" href="{{ route('hq.subscriptions.index') }}">Subscriptions</a>
    <a class="item {{ request()->routeIs('hq.plans.*') ? 'active' : '' }}" href="{{ route('hq.plans.index') }}">Plans</a>
    <a class="item {{ request()->routeIs('hq.tickets.*') ? 'active' : '' }}" href="{{ route('hq.tickets.index') }}">Support</a>
  </nav>
  <main>
    <div class="topbar">
      <h1>@yield('title')</h1>
      <span class="muted">{{ auth()->user()->name ?? '' }} · {{ now()->format('d M Y') }}</span>
    </div>
    @if(session('ok'))<div class="flash">{{ session('ok') }}</div>@endif
    @yield('content')
  </main>
</div>
</body>
</html>
