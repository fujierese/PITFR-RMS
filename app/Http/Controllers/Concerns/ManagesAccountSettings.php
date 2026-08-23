<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

trait ManagesAccountSettings
{
    public function saveNotificationPreferences(Request $request, string $route)
    {
        $user = $request->user();
        $user->notification_preferences = [
            'request_updates' => $request->boolean('request_updates'),
            'security_alerts' => $request->boolean('security_alerts'),
        ];
        $user->save();

        return redirect()->route($route)->with('success', 'Notification preferences updated successfully.');
    }

    public function saveSignature(Request $request, string $route)
    {
        $request->validate([
            'e_signature_file' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
        ]);

        $user = $request->user();
        $file = $request->file('e_signature_file');
        $filename = $user->id . '_' . now()->format('YmdHis') . '.' . strtolower($file->getClientOriginalExtension());
        $path = 'documents/e_signature/users';

        if ($user->e_signature_file) {
            Storage::disk('local')->delete($path . '/' . $user->e_signature_file);
        }

        $file->storeAs($path, $filename, 'local');
        $user->e_signature_file = $filename;
        $user->save();

        return redirect()->route($route)->with('success', 'E-signature updated successfully.');
    }
}
