<?php

namespace App\Models;

use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'address', 'phone', 'email', 'tin', 'daily_sales_target', 'logo_path', 'favicon_path'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_sales_target' => 'decimal:2',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->first();
    }

    public static function documentName(): string
    {
        $name = static::query()->value('name');

        return filled($name) ? (string) $name : (string) config('app.name');
    }

    /**
     * @return array{name: ?string, address: ?string, phone: ?string, email: ?string, tin: ?string, logo_url: ?string, logo_data_uri: ?string}
     */
    public function letterhead(): array
    {
        return [
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'tin' => $this->tin,
            'logo_url' => $this->logoUrl(),
            'logo_data_uri' => $this->logoDataUri(),
        ];
    }

    public function logoUrl(): ?string
    {
        return $this->publicUrl($this->logo_path);
    }

    public function faviconUrl(): ?string
    {
        return $this->publicUrl($this->favicon_path);
    }

    public function logoDataUri(): ?string
    {
        if ($this->logo_path === null || ! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($this->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($this->logo_path));
    }

    private function publicUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
