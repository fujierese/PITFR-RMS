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
            'e_signature_file' => [
                'required',
                'file',
                'mimes:png',
                'mimetypes:image/png',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $path = $value->getRealPath();
                    $image = $path ? @getimagesize($path) : false;
                    $detectedMime = $path ? @mime_content_type($path) : false;

                    if ($image === false || ($image['mime'] ?? null) !== 'image/png' || $detectedMime !== 'image/png') {
                        $fail('The e-signature must be a valid PNG image.');
                        return;
                    }

                    [$width, $height] = $image;
                    $ratio = $height > 0 ? $width / $height : 0;
                    if ($width < 200 || $height < 50 || $width > 2400 || $height > 1200 || $ratio < 1.3 || $ratio > 6) {
                        $fail('The e-signature must be landscape-oriented and between 200x50 and 2400x1200 pixels.');
                    }
                },
            ],
            'e_signature_confirmation' => ['accepted'],
        ]);

        $user = $request->user();
        $file = $request->file('e_signature_file');
        $filename = $user->id . '_' . now()->format('YmdHis') . '.png';
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
