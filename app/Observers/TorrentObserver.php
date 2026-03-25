<?php

namespace App\Observers;

use App\Enums\ModerationStatus;
use App\Jobs\SendTelegramNotification;
use App\Models\Torrent;

class TorrentObserver
{
    public function updated(Torrent $torrent): void
    {
        if (
            $torrent->wasChanged('status')
            && $torrent->status === ModerationStatus::APPROVED
            && $torrent->getOriginal('status') !== ModerationStatus::APPROVED->value
        ) {
            SendTelegramNotification::dispatch($torrent);
        }
    }
}
