<?php

declare(strict_types=1);

namespace Src\Clinic\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Clinic\Application\Doctors\DoctorData;
use Src\Clinic\Domain\FeeMode;
use Src\Clinic\Domain\FeeRule;
use Src\Clinic\Domain\FeeScheme;
use Src\Clinic\Domain\FeeTier;
use Src\Clinic\Domain\InvalidDoctorData;
use Src\Clinic\Domain\ScheduleSlot;
use Src\Clinic\Domain\WeeklySchedule;

/**
 * MST-02. Persentase jasa diisi dalam persen (maksimal dua desimal, contoh "32.5") dan disimpan sebagai basis poin;
 * nominal dalam rupiah bulat. Tidak ada angka pecahan yang dihitung (M4).
 */
final class DoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin diperiksa middleware can:setting.manage pada route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $mode = Rule::enum(FeeMode::class);

        return [
            'name' => ['required', 'string', 'max:255'],
            'sip_no' => ['nullable', 'string', 'max:100'],
            'specialty' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'string', 'size:26'],
            'user_id' => ['nullable', 'string', 'size:26'],
            'fee_mode' => ['required', $mode],
            'fee_value' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'fee_overrides' => ['nullable', 'array', 'max:100'],
            'fee_overrides.*.code' => ['required', 'string', 'max:30', 'distinct'],
            'fee_overrides.*.mode' => ['required', $mode],
            'fee_overrides.*.value' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'fee_tiers' => ['nullable', 'array', 'max:'.FeeScheme::MAX_TIERS],
            'fee_tiers.*.up_to' => ['nullable', 'string', 'regex:/^\d{1,12}$/'],
            'fee_tiers.*.rate' => ['required', 'string', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'fee_lab_share' => ['nullable', 'string', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'schedule' => ['nullable', 'array', 'max:50'],
            'schedule.*.weekday' => ['required', 'integer', 'between:1,7'],
            'schedule.*.start' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'schedule.*.end' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama dokter wajib diisi.',
            'fee_value.required' => 'Nilai jasa wajib diisi.',
            'fee_value.regex' => 'Nilai jasa harus angka tidak negatif.',
            'fee_overrides.*.code.distinct' => 'Kode tindakan pengecualian tidak boleh ganda.',
            'fee_overrides.*.value.regex' => 'Nilai jasa pengecualian harus angka tidak negatif.',
            'fee_tiers.*.up_to.regex' => 'Batas tingkat berupa rupiah bulat, tanpa titik atau koma.',
            'fee_tiers.*.rate.required' => 'Persentase tingkat jasa wajib diisi.',
            'fee_tiers.*.rate.regex' => 'Persentase tingkat jasa harus angka tidak negatif.',
            'fee_lab_share.regex' => 'Beban biaya lab harus angka persen tidak negatif.',
            'schedule.*.start.regex' => 'Jam mulai harus berformat JJ:MM.',
            'schedule.*.end.regex' => 'Jam selesai harus berformat JJ:MM.',
        ];
    }

    /**
     * @throws InvalidDoctorData bila nilai melanggar aturan domain (persentase di atas 100, jadwal bertumpuk)
     */
    public function toData(): DoctorData
    {
        /** @var array<string, mixed> $v */
        $v = $this->validated();

        $overrides = [];
        foreach ((array) ($v['fee_overrides'] ?? []) as $row) {
            $overrides[(string) $row['code']] = $this->rule((string) $row['mode'], (string) $row['value']);
        }

        $tiers = [];
        foreach (array_values((array) ($v['fee_tiers'] ?? [])) as $row) {
            $upTo = isset($row['up_to']) && $row['up_to'] !== '' ? (int) $row['up_to'] : null;
            $tiers[] = new FeeTier($upTo, $this->basisPoints((string) $row['rate']));
        }
        $labShareText = (string) ($v['fee_lab_share'] ?? '');
        $labShare = $this->basisPoints($labShareText === '' ? '0' : $labShareText);

        $slots = [];
        foreach ((array) ($v['schedule'] ?? []) as $row) {
            $slots[] = new ScheduleSlot((int) $row['weekday'], (string) $row['start'], (string) $row['end']);
        }

        return new DoctorData(
            name: (string) $v['name'],
            sipNo: $this->nullable($v['sip_no'] ?? null),
            specialty: $this->nullable($v['specialty'] ?? null),
            branchId: $this->nullable($v['branch_id'] ?? null),
            userId: $this->nullable($v['user_id'] ?? null),
            feeScheme: new FeeScheme($this->rule((string) $v['fee_mode'], (string) $v['fee_value']), $overrides, $tiers, $labShare),
            schedule: new WeeklySchedule($slots),
        );
    }

    private function rule(string $mode, string $value): FeeRule
    {
        $mode = FeeMode::from($mode);

        return new FeeRule($mode, $mode === FeeMode::Percentage ? $this->basisPoints($value) : $this->wholeRupiah($value));
    }

    private function basisPoints(string $percent): int
    {
        [$whole, $fraction] = array_pad(explode('.', $percent, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    private function wholeRupiah(string $value): int
    {
        if (str_contains($value, '.')) {
            throw new InvalidDoctorData('doctor.invalid_fee', 'Nominal jasa harus rupiah bulat.');
        }

        return (int) $value;
    }

    private function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
