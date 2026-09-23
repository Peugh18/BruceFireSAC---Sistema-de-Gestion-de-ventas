<?php

use App\Models\User;

test('redirects guests to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

test('redirects an authenticated user straight to their team dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirect(route('dashboard', [
        'current_team' => $user->currentTeam->slug,
    ]));
});
