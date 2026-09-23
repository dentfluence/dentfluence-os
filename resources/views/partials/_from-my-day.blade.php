{{--
    "← Back to My Day", shown only when you arrived from it.

    WHY IT EXISTS: My Day rows that cannot carry a one-click button open the
    module instead. Without a way back, the page you were working is a browser
    Back button away at best — and a receptionist who loses her place twice
    stops using the list. The module page is unchanged for everyone who did
    not come from My Day; the bar simply is not there.

    The marker survives the action too: the module's redirect goes back to the
    URL that carried ?from=my-day, so the bar is still on screen afterwards.
--}}
@if(request('from') === 'my-day')
    <a href="{{ route('my-day') }}"
       style="display:inline-flex;align-items:center;gap:7px;margin-bottom:14px;padding:6px 13px;
              font-family:'Inter',sans-serif;font-size:12.5px;font-weight:600;text-decoration:none;
              color:#6a0f70;background:#faf6fc;border:1.5px solid #e2d6ea;border-radius:8px;">
        <span style="font-size:15px;line-height:1;">&lsaquo;</span> Back to My Day
    </a>
@endif
