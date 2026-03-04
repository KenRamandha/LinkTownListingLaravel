<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Model UserProfile - Representasi tabel user_profiles
class UserProfile extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'employee_code',
        'name',
        'phone',
        'avatar_url',
        'department_id',
        'position',
        'join_date',
        'resign_date',
        'no_ktp',
        'alamat_ktp',
        'tanggal_lahir',
        'gender',
        'stat_nikah',
        'stat_pajak',
        'stat_karyawan',
        'no_npwp',
        'no_bpjs_sehat',
        'no_bpjs_kerja',
        'no_kontrak',
        'no_pkwt',
        'mulai_pkwt',
        'akhir_pkwt',
        'bank',
        'no_rekening',
        'nama_rekening',
        'izin_cuti',
        'izin_telat',
        'izin_masuk',
        'izin_pulang',
        'gaji_pokok',
        'lembur',
        'transport',
        'thr',
        'kehadiran',
        'bonus_pribadi',
        'bonus_team',
        'pot_izin',
        'pot_mangkir',
        'pot_telat',
        'pot_kasbon',
        'pot_bpjs_sehat',
        'pot_bpjs_kerja',
        'tunjangan_bpjs_sehat',
        'tunjangan_bpjs_kerja',
        'tunjangan_pajak',
    ];

    protected $casts = [
        'join_date' => 'date',
        'resign_date' => 'date',
        'tanggal_lahir' => 'date',
        'mulai_pkwt' => 'date',
        'akhir_pkwt' => 'date',
        'no_rekening' => 'float',
        'gaji_pokok' => 'float',
        'lembur' => 'float',
        'transport' => 'float',
        'thr' => 'float',
        'kehadiran' => 'float',
        'bonus_pribadi' => 'float',
        'bonus_team' => 'float',
        'pot_izin' => 'float',
        'pot_mangkir' => 'float',
        'pot_telat' => 'float',
        'pot_kasbon' => 'float',
        'pot_bpjs_sehat' => 'float',
        'pot_bpjs_kerja' => 'float',
        'tunjangan_bpjs_sehat' => 'float',
        'tunjangan_bpjs_kerja' => 'float',
        'tunjangan_pajak' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
