{{-- "What to do next" once this feature has produced something. --}}
@php
    $isOwner = ! auth()->user()->isSeat();
    $hasYoutubeScript = $completed->contains('module', 'youtube_script');
    $hasYoutubeMeta = $completed->contains('module', 'youtube_metadata');
    [$nextText, $nextHref, $nextAction] = match ($featureKey) {
        'blog' => ['Publish the article on your blog or Medium, then add it to your content calendar so you remember to share it on LinkedIn and X.', route('calendar.index'), 'Open content calendar'],
        'linkedin' => ['Send your first connection messages today. When people reply, paste their message into the reply assistant (or use the Chrome extension) for a perfect answer.', route('offers.linkedin.reply-assistant', $offer), 'Open reply assistant'],
        'youtube' => $hasYoutubeScript && ! $hasYoutubeMeta
            ? ['Your script is ready. Record the video, then generate the title, description and tags above so it ranks.', null, null]
            : ['Upload the video with the title, description and tags, then turn the same script into a short UGC clip.', route('offers.feature', [$offer, 'ugc']), 'Make a UGC video'],
        'ugc' => ['Post the video on TikTok, Instagram Reels and YouTube Shorts using the captions from your script\'s posting pack — then schedule the next one.', route('calendar.index'), 'Open content calendar'],
        'x' => ['Post the thread, pin it to your profile, and reply to every comment in the first hour.', route('offers.feature', [$offer, 'linkedin']), 'Repurpose for LinkedIn'],
        'tiktok' => ['Film it on your phone using the on-screen cues — or let an AI presenter read it for you.', route('offers.feature', [$offer, 'ugc']), 'Turn it into an AI video'],
        'pinterest' => ['Design each pin in Canva using its image prompt, pin all three to the suggested board, and keep the winner.', route('calendar.index'), 'Schedule in calendar'],
        'nurture' => ['Send email 1 now, then follow the suggested timing for the rest. Opens and clicks show up on each email.', $isOwner ? route('crm.index') : null, $isOwner ? 'Open your CRM' : null],
        'competitor' => ['Use the differentiation opportunity as the hook of your next piece of content.', route('offers.feature', [$offer, 'blog']), 'Write a blog article with it'],
        'localize' => ['Publish the localized copy to that market\'s audience — it already uses local spelling, currency and disclosure wording.', route('offers.show', $offer), 'Back to product hub'],
        default => ['Keep the momentum — pick the next tool for this product.', route('offers.show', $offer), 'Product hub'],
    };
@endphp
<x-ui.next-step :href="$nextHref" :action="$nextAction">{{ $nextText }}</x-ui.next-step>
