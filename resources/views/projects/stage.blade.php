<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $project->name }} — Tela</title>
    @vite(['resources/js/stage.js'])
    <style>
        html, body { margin: 0; height: 100%; background: #000; color: #f2f2f2; overflow: hidden; }
        #stage { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: #000; }
        #frame { position: relative; background: #000; overflow: hidden; }
        #frame img, #frame video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; background: #000; }
        #frame video { display: none; }
        #frame.mode-video img { display: none; }
        #frame.mode-video video { display: block; }
        #frame.mode-text img, #frame.mode-text video { display: none; }
        #text { position: absolute; inset: 0; display: flex; flex-direction: column; padding: 8%; box-sizing: border-box; pointer-events: none; }
        #text h1 { margin: 0; font-weight: normal; font-family: Georgia, "Times New Roman", serif; line-height: 1.25; white-space: pre-line; }
        #text p { margin: 0.8em 0 0; font-family: "Segoe UI", sans-serif; letter-spacing: 0.12em; text-transform: uppercase; color: #c8c8c8; }
        #text.cc { justify-content: flex-end; background: linear-gradient(transparent, rgba(0,0,0,.72) 70%); }
        #text.cc h1 { font-family: "Segoe UI", sans-serif; font-size: 1.6vw; line-height: 1.35; }
        #bar { position: fixed; left: 0; right: 0; bottom: 0; height: 3px; background: #1a1a1a; }
        #bar i { display: block; height: 100%; width: 0; background: #e8e8e8; }
        #clock { position: fixed; top: 14px; left: 18px; font: 13px/1 "Segoe UI", sans-serif; font-variant-numeric: tabular-nums; letter-spacing: .06em; color: rgba(255,255,255,.7); opacity: 0; transition: opacity .25s; }
        body.paused #clock, body.show-ui #clock { opacity: 1; }
        #empty { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; font-family: "Segoe UI", sans-serif; color: #9a9a9a; }
        body.empty #empty { display: flex; }
        body.empty #stage, body.empty #bar, body.empty #clock { display: none; }
    </style>
</head>
<body
    data-project="{{ $project->id }}"
    data-aspect="{{ $aspect }}"
    data-cc="{{ request()->boolean('cc') ? '1' : '0' }}"
>
    <div id="stage"><div id="frame" class="mode-text"><img alt=""><video playsinline muted></video><div id="text"><h1></h1><p></p></div></div></div>
    <div id="clock"></div>
    <div id="bar"><i></i></div>
    <div id="empty">Este projeto ainda não tem slides.</div>
</body>
</html>
