<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBroadcastJob;
use App\Models\Announcement;
use App\Models\Broadcast;
use App\Services\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Komunikasi ke pelanggan: banner pengumuman di Console dan broadcast email. */
class CommsController extends Controller
{
    public function announcements()
    {
        return view('admin.comms.announcements', ['items' => Announcement::latest()->limit(50)->get()]);
    }

    public function announcementStore(Request $request)
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'body' => ['required', 'string', 'max:2000'],
            'tone' => ['required', Rule::in(['info', 'success', 'warn', 'danger'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        $a = Announcement::create($d + ['active' => true, 'created_by' => $request->user()->id]);
        AdminAudit::record('announcement.created', 'announcement', $a->id, null, ['title' => $a->title]);

        return back()->with('success', 'Pengumuman dibuat. Tampil di Console semua pelanggan selama masa tayangnya.');
    }

    public function announcementToggle(Announcement $announcement)
    {
        $announcement->update(['active' => ! $announcement->active]);
        AdminAudit::record('announcement.toggled', 'announcement', $announcement->id, null, ['active' => $announcement->active]);

        return back()->with('success', $announcement->active ? 'Pengumuman diaktifkan.' : 'Pengumuman dinonaktifkan.');
    }

    public function announcementDestroy(Announcement $announcement)
    {
        AdminAudit::record('announcement.deleted', 'announcement', $announcement->id, null, ['title' => $announcement->title]);
        $announcement->delete();

        return back()->with('success', 'Pengumuman dihapus.');
    }

    public function broadcasts(Request $request)
    {
        $audience = $request->query('audience', 'all');

        return view('admin.comms.broadcasts', [
            'items' => Broadcast::with('creator:id,name')->latest()->limit(30)->get(),
            'audiences' => Broadcast::audiences(),
            'audience' => $audience,
            'count' => count(Broadcast::recipientsFor(array_key_exists($audience, Broadcast::audiences()) ? $audience : 'all')),
        ]);
    }

    public function broadcastSend(Request $request)
    {
        $d = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::in(array_keys(Broadcast::audiences()))],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Centang konfirmasi sebelum mengirim.']);

        $recipients = count(Broadcast::recipientsFor($d['audience']));
        if ($recipients === 0) {
            return back()->withInput()->with('error', 'Tidak ada penerima pada audiens ini.');
        }

        $b = Broadcast::create([
            'subject' => $d['subject'], 'body' => $d['body'], 'audience' => $d['audience'],
            'recipients' => $recipients, 'status' => 'sending', 'created_by' => $request->user()->id,
        ]);
        AdminAudit::record('broadcast.sent', 'broadcast', $b->id, null, ['audience' => $d['audience'], 'recipients' => $recipients, 'subject' => $d['subject']]);
        SendBroadcastJob::dispatch($b->id);

        return redirect()->route('admin.comms.broadcasts')->with('success', "Broadcast dijadwalkan ke {$recipients} penerima.");
    }
}
