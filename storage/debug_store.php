<?php
// Temporary debug script to test store method under full Laravel environment
$user = App\Models\User::first();
if ($user) {
    Auth::login($user);
    $receiver = App\Models\User::where('id', '!=', $user->id)->first();
    $image = App\Models\Image::where('user_id', $user->id)->first();
    if ($receiver && $image) {
        $request = new Illuminate\Http\Request([
            'receiver_id' => $receiver->id,
            'image_id' => $image->id,
            'body' => 'Test image send'
        ]);
        try {
            $controller = app(App\Http\Controllers\Api\V1\MongoChatController::class);
            $response = $controller->store($request);
            echo "STATUS: " . $response->getStatusCode() . "\n";
            echo "CONTENT: " . $response->getContent() . "\n";
        } catch (\Exception $e) {
            echo "EXCEPTION: " . $e->getMessage() . "\n";
        }
    } else {
        echo "No receiver or image found\n";
    }
} else {
    echo "No user found\n";
}
