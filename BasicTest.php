<?php

namespace Tests\Feature;

use App\Rating;
use Tests\TestCase;

class BasicTest extends TestCase
{
    public function testPreview()
    {
        $this->get('/rating/preview/list')->assertSee('Rating list');
    }

    public function testApiPreview()
    {
        $this->get('/api/rating/preview')->assertSuccessful();
        $this->get('/api/rating/preview')->assertStatus(200);
    }

    public function testApiSave() {

        $dummyParams = ['author' => 'dummyDummy', 'rating' => 5, 'post_id' => 999, 'post_title' => 'Dummy', 'comment' => 'Dummy'];

        $this->post('/api/rating/save')->assertStatus(302);
        $this->post('/api/rating/save', $dummyParams)->assertStatus(200);

        $dummyParams['comment'] = '';

        $this->post('/api/rating/save', $dummyParams)->assertStatus(302);

        Rating::where('author', $dummyParams['author'])->forceDelete();
    }
}
