<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalAccountSessionTest extends TestCase
{
    public function test_separate_login_creates_unique_local_hosts_without_touching_the_current_session(): void
    {
        $first = $this->withSession(['account_marker' => 'original'])->get('http://127.0.0.1:8000/dang-nhap-phien-rieng');
        $first->assertStatus(303)->assertSessionHas('account_marker', 'original');
        $url = $first->headers->get('Location');
        $this->assertMatchesRegularExpression('#\Ahttp://phien-[a-f0-9]{32}\.localhost:8000/dang-nhap\z#', $url);
        $second = $this->get('http://127.0.0.1:8000/dang-nhap-phien-rieng')->assertStatus(303);
        $this->assertNotSame($url, $second->headers->get('Location'));
    }

    public function test_separate_login_can_open_another_workspace_from_an_existing_workspace(): void
    {
        $host = 'phien-'.str_repeat('a', 32).'.localhost';
        $response = $this->get('http://'.$host.':8000/dang-nhap-phien-rieng')->assertStatus(303);
        $this->assertNotSame($host, parse_url($response->headers->get('Location'), PHP_URL_HOST));
        $this->assertSame('/dang-nhap', parse_url($response->headers->get('Location'), PHP_URL_PATH));
    }

    public function test_separate_login_is_disabled_for_external_hosts_and_production(): void
    {
        $this->get('http://example.test/dang-nhap-phien-rieng')->assertNotFound();
        $this->app['env'] = 'production';
        $this->get('http://127.0.0.1:8000/dang-nhap-phien-rieng')->assertNotFound();
    }

    public function test_separate_login_requires_host_only_cookies(): void
    {
        config(['session.domain' => '.localhost']);
        $this->get('http://127.0.0.1:8000/dang-nhap-phien-rieng')->assertNotFound();
    }
}
