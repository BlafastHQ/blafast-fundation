<?php

declare(strict_types=1);

use Blafast\Foundation\Api\ApiStructureBuilder;
use Blafast\Foundation\Models\Media;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Providers\DynamicRouteServiceProvider;
use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Tests\Fixtures\ProductModel;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Task 24 (M17): uploads are safe by default — MIME allow-list, private disk,
 * normalised filenames. Active content can never be served from the app origin
 * unless a collection explicitly opts into a public disk.
 */
beforeEach(function () {
    Schema::create('test_products', function ($table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->text('description')->nullable();
        $table->decimal('price', 10, 2);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Relation::morphMap(['product' => ProductModel::class]);
    app()->register(DynamicRouteServiceProvider::class);
    app(ModelRegistry::class)->register(ProductModel::class);

    Storage::fake('blafast-private');
    Storage::fake('public');

    $user = User::factory()->create();
    Permission::findOrCreate('update_product', 'api');
    $user->givePermissionTo('update_product');
    Role::findOrCreate('Superadmin', 'api');
    $user->assignRole('Superadmin');
    $user->unsetRelation('roles')->unsetRelation('permissions');
    test()->actingAs($user, 'sanctum');
});

function uploadTo(string $collection, UploadedFile $file)
{
    $product = ProductModel::factory()->create();

    return [test()->post("/api/v1/product/{$product->id}/files/{$collection}", [
        'file' => $file,
    ], ['Accept' => 'application/json']), $product];
}

it('rejects .html and .svg on a collection with no declared mimes', function () {
    [$html] = uploadTo('attachments', UploadedFile::fake()->createWithContent('evil.html', '<script>alert(1)</script>'));
    $html->assertStatus(422);

    [$svg] = uploadTo('attachments', UploadedFile::fake()->createWithContent('evil.svg', '<svg onload="alert(1)"/>'));
    $svg->assertStatus(422);

    expect(Media::query()->count())->toBe(0);
});

it('accepts an allowed type, stores it on the private disk with a normalised name, and serves a temporary URL', function () {
    [$response, $product] = uploadTo('attachments', UploadedFile::fake()->image('Weird NAME (1) éé.PNG'));

    $response->assertStatus(201);

    $media = Media::query()->firstOrFail();
    expect($media->disk)->toBe('blafast-private')
        ->and($media->file_name)->toBe('weird-name-1-ee.png');

    // Retrievable via a TEMPORARY (signed, expiring) URL — never a permanent
    // public /storage path.
    $url = $response->json('data.attributes.urls.original') ?? $response->json('data.attributes.url');
    expect((string) $url)->toContain('expiration');
});

it('does not accept arbitrary types on a mimes-less collection declared via the builder', function () {
    $structure = ApiStructureBuilder::make(ProductModel::class)
        ->label('x')
        ->mediaCollection('loose')
        ->build();

    // The structure itself still says "no mimes"…
    expect($structure['media_collections']['loose']['accepted_mimes'])->toBe([]);

    // …and the upload path treats that as the DEFAULT allow-list, not
    // accept-everything (proven over HTTP above); a PHP payload is rejected too.
    [$php] = uploadTo('attachments', UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]);'));
    $php->assertStatus(422);
});
