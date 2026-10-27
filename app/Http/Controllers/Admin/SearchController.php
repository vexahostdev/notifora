<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;

/** Pencarian terpadu untuk support: user, workspace, pembayaran, pesan, email. */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $r = ['users' => collect(), 'workspaces' => collect(), 'payments' => collect(), 'messages' => collect(), 'emails' => collect()];

        if (mb_strlen($q) >= 2) {
            $like = "%$q%";
            $r['users'] = User::where(fn ($w) => $w->where('email', 'like', $like)->orWhere('name', 'like', $like))->limit(10)->get();
            $r['workspaces'] = Workspace::with('owner:id,email')->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('slug', 'like', $like)->orWhere('billing_email', 'like', $like)->orWhere('id', $q))->limit(10)->get();
            $r['payments'] = Payment::with('workspace:id,name')->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('external_id', 'like', $like)->orWhere('provider_invoice_id', $q))->limit(10)->get();
            $r['messages'] = MessageLog::withoutGlobalScopes()->with('environment.workspace:id,name')->where(fn ($w) => $w->where('id', $q)->orWhere('external_message_id', $q))->limit(10)->get();
            $r['emails'] = EmailLog::where(fn ($w) => $w->where('recipient', 'like', $like)->orWhere('subject', 'like', $like))->latest('id')->limit(10)->get();
        }

        return view('admin.search', ['q' => $q, 'r' => $r, 'total' => collect($r)->sum(fn ($c) => $c->count())]);
    }
}
