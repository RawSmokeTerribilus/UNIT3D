<?php

namespace App\Jobs;

use App\Models\Torrent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Torrent $torrent)
    {
    }

    public function handle(): void
    {
        try {
            $token  = config('services.telegram.token');
            $chatId = config('services.telegram.chat_id');
            $topicId = config('services.telegram.topic_id');

            if (empty($token) || empty($chatId)) {
                Log::error('Telegram SendNotification: Missing required configuration', [
                    'token_empty' => empty($token),
                    'chat_id_empty' => empty($chatId),
                ]);
                return;
            }

            $torrent = $this->torrent;
            
            $codec = 'N/A';
            $audio = 'N/A';
            
                    if (!empty($torrent->mediainfo)) {
            if (preg_match("/(?s)Video.*?Format\s*:\s*([^
]+)/", $torrent->mediainfo, $v)) $codec = trim($v[1]);
            if (preg_match("/(?s)Audio.*?Format\s*:\s*([^
]+)/", $torrent->mediainfo, $a)) $audio = trim($a[1]);
        }

            $poster = $this->resolvePosterUrl($torrent);
            $url = route('torrents.show', ['id' => $torrent->id]);
            $size = $this->formatSize((int) $torrent->size);

            $caption = "🎬 <b>{$torrent->name}</b>\n"
                     . "📦 <b>Size:</b> {$size}\n"
                     . "🎞 <b>Codec:</b> {$codec}\n"
                     . "🔊 <b>Audio:</b> {$audio}";

            if (mb_strlen($caption) > 1024) {
                $caption = mb_substr($caption, 0, 1020) . '...';
            }

            $buttons = [];
            
            if ($torrent->imdb > 0) {
                $imdbId = str_pad((string) $torrent->imdb, 7, '0', STR_PAD_LEFT);
                $buttons[] = ['text' => 'IMDb', 'url' => "https://www.imdb.com/title/tt{$imdbId}"];
            }

            if (!empty($torrent->tmdb_movie_id)) {
                $buttons[] = ['text' => 'TMDb', 'url' => "https://www.themoviedb.org/movie/{$torrent->tmdb_movie_id}"];
            } elseif (!empty($torrent->tmdb_tv_id)) {
                $buttons[] = ['text' => 'TMDb', 'url' => "https://www.themoviedb.org/tv/{$torrent->tmdb_tv_id}"];
            }
            
            $trailerUrl = $this->resolveTrailerUrl($torrent);
            if ($trailerUrl) {
                $buttons[] = ['text' => 'Trailer', 'url' => $trailerUrl];
            }

            $buttons[] = ['text' => 'View Torrent', 'url' => $url];

            $payload = [
                'chat_id'           => $chatId,
                'message_thread_id' => (int) $topicId,
                'photo'             => $poster,
                'caption'           => $caption,
                'parse_mode'        => 'HTML',
            ];

            if (!empty($buttons)) {
                $payload['reply_markup'] = ['inline_keyboard' => array_chunk($buttons, 2)];
            }

            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendPhoto", $payload);
            
            if (!$response->successful()) {
                Log::error('Telegram SendNotification: API returned error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'torrent_id' => $torrent->id,
                ]);
                return;
            }

            Log::info('Telegram SendNotification: Success', ['torrent_id' => $torrent->id, 'message_id' => $response->json('result.message_id')]);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Telegram SendNotification: HTTP Request failed', [
                'status' => $e->response?->status(),
                'body' => $e->response?->body(),
                'torrent_id' => $this->torrent->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram SendNotification: Unexpected error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'torrent_id' => $this->torrent->id,
            ]);
        }
    }

    private function resolvePosterUrl(Torrent $torrent): string
    {
        $poster = $torrent->movie?->poster ?? $torrent->tv?->poster;

        if (!$poster) {
            return 'https://via.placeholder.com/600x900?text=No+Poster';
        }

        if (str_starts_with($poster, 'http://') || str_starts_with($poster, 'https://')) {
            return $poster;
        }

        return 'https://via.placeholder.com/600x900?text=No+Poster';
    }

    private function resolveTrailerUrl(?Torrent $torrent): ?string
    {
        $trailerId = $torrent?->movie?->trailer;

        if (!$trailerId) {
            return null;
        }

        if (str_starts_with($trailerId, 'http://') || str_starts_with($trailerId, 'https://')) {
            return $trailerId;
        }

        return "https://www.youtube.com/watch?v={$trailerId}";
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) {
            return round($bytes / 1_073_741_824, 2) . ' GiB';
        }

        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2) . ' MiB';
        }

        if ($bytes >= 1_024) {
            return round($bytes / 1_024, 2) . ' KiB';
        }

        return $bytes . ' B';
    }
}
