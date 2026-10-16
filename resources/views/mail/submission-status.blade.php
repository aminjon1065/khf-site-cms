{{-- Email to a citizen about a new stage of their appeal: the site, not the
     CMS, in the header, and the Committee's name in the citizen's language. --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="$siteUrl">
{{ $signature }}
</x-mail::header>
</x-slot:header>

**{{ $greeting }}**

{{ $changed }}

{{ $meaning }}

{{ $check }} [{{ $checkUrl }}]({{ $checkUrl }})

{{ $automatic }}

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $signature }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
