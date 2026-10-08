@props(['title'])
<details class="afs-g-trouble">
    <summary>{{ $title }}</summary>
    <div>{{ $slot }}</div>
</details>
