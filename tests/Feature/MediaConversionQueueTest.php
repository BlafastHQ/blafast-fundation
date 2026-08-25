<?php

declare(strict_types=1);

use Blafast\Foundation\Api\ApiStructureBuilder;
use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Traits\ExposesApiStructure;
use Blafast\Foundation\Traits\HasMediaCollections;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\HasMedia;

/**
 * Task 25 (L2): conversions honour the configured queue behaviour instead of
 * the old unconditional nonQueued() (which forced every conversion inline in
 * the upload request).
 */
class ConversionQueueModel extends Model implements HasApiStructure, HasMedia
{
    use ExposesApiStructure;
    use HasMediaCollections;
    use HasUuids;

    protected $table = 'conversion_queue_models';

    public static function apiStructure(): array
    {
        return ApiStructureBuilder::make(self::class)
            ->label('x')
            ->mediaCollection('gallery', conversions: ['thumb'])
            ->build();
    }
}

function thumbConversion(): Conversion
{
    $model = new ConversionQueueModel;
    $model->registerMediaConversions();

    return collect($model->mediaConversions)
        ->first(fn (Conversion $c) => $c->getName() === 'thumb');
}

it('queues conversions when the knob is on (the default)', function () {
    config()->set('blafast-fundation.media.queue_conversions', true);

    expect(thumbConversion()->shouldBeQueued())->toBeTrue();
});

it('generates conversions inline when the knob is off', function () {
    config()->set('blafast-fundation.media.queue_conversions', false);

    expect(thumbConversion()->shouldBeQueued())->toBeFalse();
});
