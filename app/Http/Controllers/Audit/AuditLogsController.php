<?php


namespace App\Http\Controllers\Audit;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AuditLogsController extends Controller
{
    // GET /api/audit/logs - Ambil log audit dengan filter
    public function index(Request $r)
    {
        try { if(!$r->user()->hasPermission('audit.view')) return $this->fail('Forbidden',403,'FORBIDDEN');
            $q=DB::table('audit_logs');
            if($uid=$r->query('user_id')) $q->where('user_id',$uid);
            if($action=$r->query('action')) $q->where('action','like',"%$action%");
            if($from=$r->query('from')) $q->where('created_at','>=',$from);
            if($to=$r->query('to')) $q->where('created_at','<=',$to);
            $data=$q->orderByDesc('created_at')->limit((int)$r->query('limit',100))->get();
            return $this->ok($data,'Audit logs');
        } catch(Throwable $e){ report($e); return $this->fail('Gagal memuat audit logs',500,'SERVER_ERROR'); }
    }
}

