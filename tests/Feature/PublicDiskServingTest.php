<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicDiskServingTest extends TestCase
{
    public function test_catalog_image_on_the_public_disk_is_served_without_a_signature(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('catalog-images/kibble.jpg', 'jpg-bytes');

        $this->get('/storage/catalog-images/kibble.jpg')->assertOk();
    }
}
