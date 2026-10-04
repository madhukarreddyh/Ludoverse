<?php

namespace Tests\Feature\Wallet;

use App\Models\ManualDeposit;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\LicensedTestCase;

/**
 * Manual (UPI) deposit claims: submit -> admin approve (credited) or
 * reject (nothing moves).
 */
class ManualDepositTest extends LicensedTestCase
{
    protected function submitClaim(User $user): ManualDeposit
    {
        Storage::fake('public');

        $this->actingAs($user)->post('/wallet/deposit/manual', [
            'amount' => 75000, // ₹750 in paise
            'utr' => 'UTR'.uniqid(),
            'screenshot' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();

        $deposit = ManualDeposit::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($deposit);
        $this->assertSame('pending', $deposit->status);

        return $deposit;
    }

    public function test_admin_approve_credits_the_wallet(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $deposit = $this->submitClaim($user);

        $this->actingAs($admin)->post("/hmkr/deposits/{$deposit->id}/approve")
            ->assertRedirect();

        $this->assertSame('approved', $deposit->fresh()->status);
        $this->assertSame($admin->id, $deposit->fresh()->reviewed_by);
        $this->assertSame(75000, app(WalletService::class)->balance($user));
    }

    public function test_admin_approve_is_idempotent_on_double_click(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $deposit = $this->submitClaim($user);

        $this->actingAs($admin)->post("/hmkr/deposits/{$deposit->id}/approve")->assertRedirect();
        // Second approve hits the status guard (no longer pending).
        $this->actingAs($admin)->post("/hmkr/deposits/{$deposit->id}/approve")->assertRedirect();

        $this->assertSame(75000, app(WalletService::class)->balance($user));
    }

    public function test_admin_reject_credits_nothing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $deposit = $this->submitClaim($user);

        $this->actingAs($admin)->post("/hmkr/deposits/{$deposit->id}/reject")
            ->assertRedirect();

        $this->assertSame('rejected', $deposit->fresh()->status);
        $this->assertSame(0, app(WalletService::class)->balance($user));
    }

    public function test_utr_must_be_unique(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $payload = fn () => [
            'amount' => 10000,
            'utr' => 'DUPLICATE_UTR_1',
            'screenshot' => UploadedFile::fake()->image('proof.jpg'),
        ];

        $this->actingAs($user)->post('/wallet/deposit/manual', $payload())->assertRedirect();

        // Same UTR again is rejected by validation.
        $this->actingAs($user)->post('/wallet/deposit/manual', $payload())
            ->assertSessionHasErrors('utr');

        $this->assertSame(1, ManualDeposit::where('utr', 'DUPLICATE_UTR_1')->count());
    }

    public function test_non_image_screenshot_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/wallet/deposit/manual', [
            'amount' => 10000,
            'utr' => 'UTR_IMG_TEST_1',
            'screenshot' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('screenshot');
    }
}
