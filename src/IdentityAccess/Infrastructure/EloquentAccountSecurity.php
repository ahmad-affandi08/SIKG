<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\IdentityAccess\Application\Account\AccountSecurity;
use Src\IdentityAccess\Domain\CurrentPasswordIncorrect;
use Src\IdentityAccess\Domain\PasswordUnchanged;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

final readonly class EloquentAccountSecurity implements AccountSecurity
{
    public function __construct(private Transaction $tx, private AuditLogger $audit, private Clock $clock) {}

    public function mustChangePassword(string $userId): bool
    {
        return User::query()->findOrFail($userId)->must_change_password;
    }

    public function changePassword(string $userId, string $current, string $new, ?string $keepSessionId): void
    {
        $user = User::query()->findOrFail($userId);

        if (! Hash::check($current, $user->password)) {
            $this->audit->record(new AuditEntry($user->id, 'user.password_change_failed', 'user', $user->id));

            throw new CurrentPasswordIncorrect;
        }
        if (Hash::check($new, $user->password)) {
            throw new PasswordUnchanged;
        }

        $this->tx->run(function () use ($user, $new, $keepSessionId): void {
            $forced = $user->must_change_password;
            $user->forceFill([
                'password' => $new,
                'must_change_password' => false,
                'password_changed_at' => $this->clock->now(),
                'remember_token' => null,
                'updated_by' => $user->id,
            ])->save();

            $this->endOtherSessions($user->id, $keepSessionId);
            $this->audit->record(new AuditEntry($user->id, 'user.password_changed', 'user', $user->id, null, ['was_temporary' => $forced]));
        });
    }

    /**
     * Sesi lain ikut berakhir (sesi lewat database, M21); bila driver lain, sesi lain berakhir saat tidak aktif.
     */
    private function endOtherSessions(string $userId, ?string $keepSessionId): void
    {
        $query = DB::table((string) config('session.table', 'sessions'))->where('user_id', $userId);
        if ($keepSessionId !== null) {
            $query->where('id', '!=', $keepSessionId);
        }
        $query->delete();
    }
}
