<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ isset($title) ? $title.' · MQMesh' : 'MQMesh · MQ2Nav meshes for EverQuest' }}</title>
<meta name="description" content="Download and share MQ2Nav navigation meshes and door files for every EverQuest zone." />
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml" />

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|jetbrains-mono:400,500" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
