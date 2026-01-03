<?php

declare(strict_types=1);

use Blafast\Foundation\Services\FileService;
use Illuminate\Support\Facades\Config;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    $this->service = app(FileService::class);
});

test('transform returns basic file structure', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getAttribute')->with('uuid')->andReturn('test-uuid-123');
    $media->shouldReceive('getAttribute')->with('name')->andReturn('Test Image');
    $media->shouldReceive('getAttribute')->with('file_name')->andReturn('test-image.jpg');
    $media->shouldReceive('getAttribute')->with('mime_type')->andReturn('image/jpeg');
    $media->shouldReceive('getAttribute')->with('size')->andReturn(102400);
    $media->shouldReceive('getAttribute')->with('disk')->andReturn('public');
    $media->shouldReceive('getGeneratedConversions')->andReturn(collect([]));
    $media->shouldReceive('getUrl')->andReturn('https://example.com/test-image.jpg');

    $result = $this->service->transform($media);

    expect($result)->toBeArray()
        ->and($result['type'])->toBe('file')
        ->and($result['id'])->toBe('test-uuid-123')
        ->and($result['attributes']['name'])->toBe('Test Image')
        ->and($result['attributes']['filename'])->toBe('test-image.jpg')
        ->and($result['attributes']['mime_type'])->toBe('image/jpeg')
        ->and($result['attributes']['size'])->toBe(102400)
        ->and($result['attributes']['urls'])->toBeArray();
});

test('transform includes URLs in attributes', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getAttribute')->andReturnUsing(function ($key) {
        return match ($key) {
            'uuid' => 'test-uuid',
            'name' => 'Test',
            'file_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'public',
            default => null,
        };
    });
    $media->shouldReceive('getGeneratedConversions')->andReturn(collect([]));
    $media->shouldReceive('getUrl')->andReturn('https://example.com/test.jpg');

    $result = $this->service->transform($media);

    expect($result['attributes'])->toHaveKey('urls')
        ->and($result['attributes']['urls'])->toHaveKey('original')
        ->and($result['attributes']['urls']['original'])->toBe('https://example.com/test.jpg');
});

test('transform with detailed flag includes additional metadata', function () {
    $createdAt = now();
    $updatedAt = now()->addHour();

    $media = mock(Media::class);
    $media->shouldReceive('getAttribute')->andReturnUsing(function ($key) use ($createdAt, $updatedAt) {
        return match ($key) {
            'uuid' => 'test-uuid',
            'name' => 'Test',
            'file_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'public',
            'custom_properties' => ['alt' => 'Test alt text'],
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'collection_name' => 'images',
            'order_column' => 1,
            default => null,
        };
    });
    $media->shouldReceive('getGeneratedConversions')->andReturn(collect([]));
    $media->shouldReceive('getUrl')->andReturn('https://example.com/test.jpg');

    $result = $this->service->transform($media, detailed: true);

    expect($result['attributes'])->toHaveKey('custom_properties')
        ->and($result['attributes']['custom_properties'])->toBe(['alt' => 'Test alt text'])
        ->and($result['attributes'])->toHaveKey('created_at')
        ->and($result['attributes'])->toHaveKey('updated_at')
        ->and($result['attributes'])->toHaveKey('collection_name')
        ->and($result['attributes']['collection_name'])->toBe('images')
        ->and($result['attributes'])->toHaveKey('order_column')
        ->and($result['attributes']['order_column'])->toBe(1);
});

test('transform without detailed flag excludes additional metadata', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getAttribute')->andReturnUsing(function ($key) {
        return match ($key) {
            'uuid' => 'test-uuid',
            'name' => 'Test',
            'file_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'public',
            default => null,
        };
    });
    $media->shouldReceive('getGeneratedConversions')->andReturn(collect([]));
    $media->shouldReceive('getUrl')->andReturn('https://example.com/test.jpg');

    $result = $this->service->transform($media, detailed: false);

    expect($result['attributes'])->not->toHaveKey('custom_properties')
        ->and($result['attributes'])->not->toHaveKey('created_at')
        ->and($result['attributes'])->not->toHaveKey('updated_at')
        ->and($result['attributes'])->not->toHaveKey('collection_name')
        ->and($result['attributes'])->not->toHaveKey('order_column');
});

test('buildUrls includes conversions when generated', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getAttribute')->with('disk')->andReturn('public');
    $media->shouldReceive('getGeneratedConversions')->andReturn(collect([
        'thumb' => true,
        'medium' => true,
        'large' => false,
    ]));
    $media->shouldReceive('getUrl')->andReturnUsing(function ($conversion = null) {
        if ($conversion === 'thumb') {
            return 'https://example.com/thumb.jpg';
        }
        if ($conversion === 'medium') {
            return 'https://example.com/medium.jpg';
        }

        return 'https://example.com/original.jpg';
    });

    Config::set('filesystems.disks.public', [
        'driver' => 'local',
        'visibility' => 'public',
    ]);

    $method = new ReflectionMethod(FileService::class, 'buildUrls');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, $media);

    expect($result)->toHaveKey('original')
        ->and($result['original'])->toBe('https://example.com/original.jpg')
        ->and($result)->toHaveKey('thumb')
        ->and($result['thumb'])->toBe('https://example.com/thumb.jpg')
        ->and($result)->toHaveKey('medium')
        ->and($result['medium'])->toBe('https://example.com/medium.jpg')
        ->and($result)->toHaveKey('large')
        ->and($result['large'])->toBeNull();
});

test('buildUrls sets null for non-generated conversions', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getAttribute')->with('disk')->andReturn('public');
    $media->shouldReceive('getGeneratedConversions')->andReturn(collect([
        'thumb' => false,
        'large' => false,
    ]));
    $media->shouldReceive('getUrl')->andReturn('https://example.com/original.jpg');

    Config::set('filesystems.disks.public', [
        'driver' => 'local',
        'visibility' => 'public',
    ]);

    $method = new ReflectionMethod(FileService::class, 'buildUrls');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, $media);

    expect($result['thumb'])->toBeNull()
        ->and($result['large'])->toBeNull();
});

test('getUrl returns public URL for public disk', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getAttribute')->with('disk')->andReturn('public');
    $media->shouldReceive('getUrl')->andReturn('https://example.com/test.jpg');

    Config::set('filesystems.disks.public', [
        'driver' => 'local',
        'visibility' => 'public',
    ]);

    $method = new ReflectionMethod(FileService::class, 'getUrl');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, $media);

    expect($result)->toBe('https://example.com/test.jpg');
});

test('getUrl returns temporary URL for private disk', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getAttribute')->with('disk')->andReturn('private');
    $media->shouldReceive('getTemporaryUrl')->andReturn('https://example.com/test.jpg?signature=abc123');

    Config::set('filesystems.disks.private', [
        'driver' => 'local',
        'visibility' => 'private',
    ]);

    $method = new ReflectionMethod(FileService::class, 'getUrl');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, $media);

    expect($result)->toContain('signature=abc123');
});

test('getUrl returns temporary URL for S3 disk', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getAttribute')->with('disk')->andReturn('s3');
    $media->shouldReceive('getTemporaryUrl')->andReturn('https://s3.amazonaws.com/test.jpg?signature=xyz789');

    Config::set('filesystems.disks.s3', [
        'driver' => 's3',
    ]);

    $method = new ReflectionMethod(FileService::class, 'getUrl');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, $media);

    expect($result)->toContain('signature=xyz789');
});

test('getUrl handles conversion parameter for public disk', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getAttribute')->with('disk')->andReturn('public');
    $media->shouldReceive('getUrl')->with('thumb')->andReturn('https://example.com/thumb.jpg');

    Config::set('filesystems.disks.public', [
        'driver' => 'local',
        'visibility' => 'public',
    ]);

    $method = new ReflectionMethod(FileService::class, 'getUrl');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, $media, 'thumb');

    expect($result)->toBe('https://example.com/thumb.jpg');
});

test('isPrivateDisk returns true for private visibility', function () {
    $config = [
        'driver' => 'local',
        'visibility' => 'private',
    ];

    $method = new ReflectionMethod(FileService::class, 'isPrivateDisk');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, $config);

    expect($result)->toBeTrue();
});

test('isPrivateDisk returns false for public visibility', function () {
    $config = [
        'driver' => 'local',
        'visibility' => 'public',
    ];

    $method = new ReflectionMethod(FileService::class, 'isPrivateDisk');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, $config);

    expect($result)->toBeFalse();
});

test('isPrivateDisk returns true for S3 driver', function () {
    $config = [
        'driver' => 's3',
    ];

    $method = new ReflectionMethod(FileService::class, 'isPrivateDisk');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, $config);

    expect($result)->toBeTrue();
});

test('isPrivateDisk returns true for s3-compatible drivers', function () {
    $config = [
        'driver' => 's3-compatible',
    ];

    $method = new ReflectionMethod(FileService::class, 'isPrivateDisk');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, $config);

    expect($result)->toBeTrue();
});

test('isPrivateDisk returns false for local driver with no visibility', function () {
    $config = [
        'driver' => 'local',
    ];

    $method = new ReflectionMethod(FileService::class, 'isPrivateDisk');
    $method->setAccessible(true);

    $result = $method->invoke($this->service, $config);

    expect($result)->toBeFalse();
});

test('transform handles null created_at gracefully', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getAttribute')->andReturnUsing(function ($key) {
        return match ($key) {
            'uuid' => 'test-uuid',
            'name' => 'Test',
            'file_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'public',
            'custom_properties' => [],
            'created_at' => null,
            'updated_at' => null,
            'collection_name' => 'images',
            'order_column' => 1,
            default => null,
        };
    });
    $media->shouldReceive('getGeneratedConversions')->andReturn(collect([]));
    $media->shouldReceive('getUrl')->andReturn('https://example.com/test.jpg');

    $result = $this->service->transform($media, detailed: true);

    // Should provide current timestamp as fallback
    expect($result['attributes'])->toHaveKey('created_at')
        ->and($result['attributes']['created_at'])->not->toBeNull();
});

test('transform includes all basic attributes in correct structure', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getAttribute')->andReturnUsing(function ($key) {
        return match ($key) {
            'uuid' => 'test-uuid-456',
            'name' => 'Document',
            'file_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 204800,
            'disk' => 'public',
            default => null,
        };
    });
    $media->shouldReceive('getGeneratedConversions')->andReturn(collect([]));
    $media->shouldReceive('getUrl')->andReturn('https://example.com/document.pdf');

    $result = $this->service->transform($media);

    expect($result)->toHaveKey('type')
        ->and($result)->toHaveKey('id')
        ->and($result)->toHaveKey('attributes')
        ->and($result['attributes'])->toHaveKeys(['name', 'filename', 'mime_type', 'size', 'urls']);
});
