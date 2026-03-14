<?php

namespace App\Models\Ijin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model TrIjin - Representasi tabel tr_ijin
 * Transaction table untuk pengajuan ijin
 */
class TrIjin extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tr_ijin';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_user',
        'jenis_form',
        'nama',
        'tgl_dari',
        'tgl_sampai',
        'tujuan',
        'remark1',
        'remark2',
        'note',
        'image_upload',
        'create_user',
        'approve_user',
        'status_ijin',
        'approved_date',
    ];

    protected $casts = [
        'tgl_dari' => 'datetime',
        'tgl_sampai' => 'datetime',
        'approved_date' => 'datetime',
    ];

    /**
     * Get the master ijin type for this transaction
     */
    public function masterIjin()
    {
        return $this->belongsTo(MsIjin::class, 'tujuan', 'jenis_form');
    }

    /**
     * Scope for filtering by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status_ijin', $status);
    }

    /**
     * Scope for pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status_ijin', 'Request');
    }

    /**
     * Scope for approved requests
     */
    public function scopeApproved($query)
    {
        return $query->where('status_ijin', 'Terima');
    }

    /**
     * Scope for rejected requests
     */
    public function scopeRejected($query)
    {
        return $query->where('status_ijin', 'Tolak');
    }

    /**
     * Scope for filtering by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('id_user', $userId);
    }

    /**
     * Check if the request is pending
     */
    public function isPending(): bool
    {
        return $this->status_ijin === 'Request';
    }

    /**
     * Check if the request is approved
     */
    public function isApproved(): bool
    {
        return $this->status_ijin === 'Terima';
    }

    /**
     * Check if the request is rejected
     */
    public function isRejected(): bool
    {
        return $this->status_ijin === 'Tolak';
    }

    /**
     * Approve the ijin request
     */
    public function approve(string $approver): void
    {
        $this->update([
            'status_ijin' => 'Terima',
            'approve_user' => $approver,
            'approved_date' => now(),
        ]);
    }

    /**
     * Reject the ijin request
     */
    public function reject(string $approver): void
    {
        $this->update([
            'status_ijin' => 'Tolak',
            'approve_user' => $approver,
            'approved_date' => now(),
        ]);
    }
}
