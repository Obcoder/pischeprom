<?php

namespace App\Jobs;

use App\Models\YandexDirectAd;
use App\Services\Yandex\YandexDirectCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendYandexDirectAdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public int $adId) {}

    public function handle(YandexDirectCampaignService $service): void
    {
        $ad = YandexDirectAd::query()->findOrFail($this->adId);

        $service->sendAd($ad);
    }
}
