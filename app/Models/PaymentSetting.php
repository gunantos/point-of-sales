<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    use HasFactory;

    public const GATEWAY_MIDTRANS = 'midtrans';

    public const GATEWAY_XENDIT = 'xendit';

    public const GATEWAY_BANK_TRANSFER = 'bank_transfer';

    public const GATEWAY_GOFOOD = 'gofood';

    public const GATEWAY_SHOPEE_FOOD = 'shopee_food';

    public const GATEWAY_GRAB_FOOD = 'grab_food';

    public const MARKETPLACE_FIELDS = [
        'gofood_enabled',
        'shopee_food_enabled',
        'grab_food_enabled',
        'gofood_client_id',
        'shopee_food_client_id',
        'grab_food_client_id',
        'gofood_sync_products',
        'shopee_food_sync_products',
        'grab_food_sync_products',
    ];

    public const SECRET_FIELDS = [
        'midtrans_server_key',
        'xendit_secret_key',
        'xendit_callback_token',
        'gofood_client_id',
        'shopee_food_client_id',
        'grab_food_client_id',
    ];

    protected $fillable = [
        'default_gateway',
        'outlet_id',
        'bank_transfer_enabled',
        'midtrans_enabled',
        'midtrans_server_key',
        'midtrans_client_key',
        'midtrans_production',
        'xendit_enabled',
        'xendit_secret_key',
        'xendit_public_key',
        'xendit_callback_token',
        'xendit_production',
        'gofood_enabled',
        'shopee_food_enabled',
        'grab_food_enabled',
        'gofood_client_id',
        'shopee_food_client_id',
        'grab_food_client_id',
        'gofood_sync_products',
        'shopee_food_sync_products',
        'grab_food_sync_products',
        'gofood_production',
        'shopee_food_production',
        'grab_food_production',
    ];

    protected $casts = [
        'outlet_id' => 'integer',
        'bank_transfer_enabled' => 'boolean',
        'midtrans_enabled' => 'boolean',
        'midtrans_production' => 'boolean',
        'xendit_enabled' => 'boolean',
        'xendit_production' => 'boolean',
        'midtrans_server_key' => 'encrypted',
        'xendit_secret_key' => 'encrypted',
        'xendit_public_key' => 'encrypted',
        'xendit_callback_token' => 'encrypted',
        'gofood_enabled' => 'boolean',
        'shopee_food_enabled' => 'boolean',
        'grab_food_enabled' => 'boolean',
        'gofood_client_id' => 'string',
        'shopee_food_client_id' => 'string',
        'grab_food_client_id' => 'string',
        'gofood_sync_products' => 'boolean',
        'shopee_food_sync_products' => 'boolean',
        'grab_food_sync_products' => 'boolean',
        'gofood_production' => 'boolean',
        'shopee_food_production' => 'boolean',
        'grab_food_production' => 'boolean',
    ];

    public static function forOutlet(?Outlet $outlet): ?self
    {
        if ($outlet) {
            $scoped = static::where('outlet_id', $outlet->id)->first();
            if ($scoped) {
                return $scoped;
            }
        }

        return static::whereNull('outlet_id')->first();
    }

    public static function forOutletOrCreate(?Outlet $outlet): self
    {
        if (! $outlet) {
            return static::firstOrCreate([], ['default_gateway' => 'cash']);
        }

        $scoped = static::where('outlet_id', $outlet->id)->first();
        if ($scoped) {
            return $scoped;
        }

        $setting = static::whereNull('outlet_id')->first();
        if ($setting) {
            $scoped = $setting->replicate();
            $scoped->outlet_id = $outlet->id;
            $scoped->save();

            return $scoped;
        }

        return static::create(['default_gateway' => 'cash', 'outlet_id' => $outlet->id]);
    }

    public function enabledGateways(?Outlet $outlet = null): array
    {
        $gateways = [];

        // Bank Transfer
        if ($this->isBankTransferReady($outlet)) {
            $gateways[] = [
                'value' => self::GATEWAY_BANK_TRANSFER,
                'label' => 'Transfer Bank',
                'description' => 'Pembayaran manual via transfer bank.',
            ];
        }

        if ($this->isGatewayReady(self::GATEWAY_MIDTRANS)) {
            $gateways[] = [
                'value' => self::GATEWAY_MIDTRANS,
                'label' => 'Midtrans',
                'description' => 'Bagikan tautan pembayaran Snap Midtrans ke pelanggan.',
            ];
        }

        if ($this->isGatewayReady(self::GATEWAY_XENDIT)) {
            $gateways[] = [
                'value' => self::GATEWAY_XENDIT,
                'label' => 'Xendit',
                'description' => 'Buat invoice otomatis menggunakan Xendit.',
            ];
        }

        return $gateways;
    }

    /**
     * Check if bank transfer is ready (enabled and has active bank accounts)
     */
    public function isBankTransferReady(?Outlet $outlet = null): bool
    {
        return $this->bank_transfer_enabled && BankAccount::active()->forOutlet($outlet)->exists();
    }

    public function isGatewayReady(string $gateway): bool
    {
        return match ($gateway) {
            self::GATEWAY_BANK_TRANSFER => $this->isBankTransferReady(),
            self::GATEWAY_MIDTRANS => $this->midtrans_enabled
            && filled($this->resolvedSecret('midtrans_server_key'))
            && filled($this->midtrans_client_key),
            self::GATEWAY_XENDIT => $this->xendit_enabled
            && filled($this->resolvedSecret('xendit_secret_key'))
            && filled($this->xendit_public_key),
            default => false,
        };
    }

    public function midtransConfig(): array
    {
        return [
            'enabled' => $this->isGatewayReady(self::GATEWAY_MIDTRANS),
            'server_key' => $this->resolvedSecret('midtrans_server_key'),
            'client_key' => $this->midtrans_client_key,
            'is_production' => $this->midtrans_production,
        ];
    }

    public function xenditConfig(): array
    {
        return [
            'enabled' => $this->isGatewayReady(self::GATEWAY_XENDIT),
            'secret_key' => $this->resolvedSecret('xendit_secret_key'),
            'public_key' => $this->xendit_public_key,
            'callback_token' => $this->resolvedSecret('xendit_callback_token'),
            'is_production' => $this->xendit_production,
        ];
    }

    public function gofoodConfig(): array
    {
        return [
            'enabled' => $this->gofood_enabled,
            'client_id' => $this->gofood_client_id,
            'client_secret' => $this->resolvedSecret('gofood_client_id'),
            'is_production' => $this->gofood_production ?? env('GOFOOD_PRODUCTION', false),
        ];
    }

    public function shopeeFoodConfig(): array
    {
        return [
            'enabled' => $this->shopee_food_enabled,
            'client_id' => $this->shopee_food_client_id,
            'client_secret' => $this->resolvedSecret('shopee_food_client_id'),
            'is_production' => $this->shopee_food_production ?? env('SHOPEE_FOOD_PRODUCTION', false),
        ];
    }

    public function grabFoodConfig(): array
    {
        return [
            'enabled' => $this->grab_food_enabled,
            'client_id' => $this->grab_food_client_id,
            'client_secret' => $this->resolvedSecret('grab_food_client_id'),
            'is_production' => $this->grab_food_production ?? env('GRAB_FOOD_PRODUCTION', false),
        ];
    }

    public function resolvedSecret(string $field): ?string
    {
        $envValue = $this->envSecretValue($field);

        if (filled($envValue)) {
            return $envValue;
        }

        return $this->getAttributeValue($field);
    }

    public function secretSource(string $field): string
    {
        if (filled($this->envSecretValue($field))) {
            return 'env';
        }

        if (filled($this->getAttributeValue($field))) {
            return 'database';
        }

        return 'none';
    }

    public function secretConfigured(string $field): bool
    {
        return filled($this->resolvedSecret($field));
    }

    public function secretManagedByEnvironment(string $field): bool
    {
        return $this->secretSource($field) === 'env';
    }

    public function maskedSecret(string $field): ?string
    {
        $value = $this->resolvedSecret($field);

        if (blank($value)) {
            return null;
        }

        $length = strlen($value);

        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        return str_repeat('•', max($length - 4, 4)).substr($value, -4);
    }

    public function paymentSettingSources(): array
    {
        return [
            'midtrans_server_key' => $this->secretMetadata('midtrans_server_key'),
            'xendit_secret_key' => $this->secretMetadata('xendit_secret_key'),
            'xendit_callback_token' => $this->secretMetadata('xendit_callback_token'),
        ];
    }

    private function secretMetadata(string $field): array
    {
        return [
            'source' => $this->secretSource($field),
            'configured' => $this->secretConfigured($field),
            'managed_by_environment' => $this->secretManagedByEnvironment($field),
            'masked' => $this->maskedSecret($field),
        ];
    }

    private function envSecretValue(string $field): ?string
    {
        return match ($field) {
            'midtrans_server_key' => config('services.midtrans.server_key'),
            'xendit_secret_key' => config('services.xendit.secret_key'),
            'xendit_callback_token' => config('services.xendit.callback_token'),
            default => null,
        };
    }
}
