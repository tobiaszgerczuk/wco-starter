<?php

return static function (array $fields, string $content): array {
    $url = trim((string) ($fields['video_url'] ?? ''));
    $video = ['provider' => '', 'embed' => '', 'file' => '', 'title' => 'Wideo'];

    if (preg_match('~(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $match) === 1) {
        $video['provider'] = 'youtube';
        $video['embed'] = 'https://www.youtube-nocookie.com/embed/' . $match[1] . '?autoplay=1&rel=0';
    } elseif (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $match) === 1) {
        $video['provider'] = 'vimeo';
        $video['embed'] = 'https://player.vimeo.com/video/' . $match[1] . '?autoplay=1&dnt=1';
    } elseif (preg_match('~\.(mp4|webm|ogg)(\?.*)?$~i', $url) === 1) {
        $video['file'] = esc_url($url);
    }

    return [
        'video' => $video,
        'poster_url' => !empty($fields['video_poster']) ? (string) wp_get_attachment_image_url((int) $fields['video_poster'], 'large') : '',
    ];
};
