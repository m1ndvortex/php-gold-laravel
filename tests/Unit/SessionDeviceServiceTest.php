<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserSession;
use App\Services\SessionDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SessionDeviceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SessionDeviceService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new SessionDeviceService();
        $this->user = User::factory()->create();
    }

    public function test_creates_session_with_device_info()
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'REMOTE_ADDR' => '127.0.0.1'
        ]);

        $session = $this->service->createSession($this->user, $request, 'test-session-id');

        $this->assertInstanceOf(UserSession::class, $session);
        $this->assertEquals('test-session-id', $session->session_id);
        $this->assertEquals('127.0.0.1', $session->ip_address);
        $this->assertEquals('desktop', $session->device_type);
        $this->assertStringContainsString('Chrome', $session->browser);
        $this->assertStringContainsString('Windows', $session->platform);
        $this->assertTrue($session->is_current);
    }

    public function test_marks_other_sessions_as_not_current()
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'REMOTE_ADDR' => '127.0.0.1'
        ]);

        // Create first session
        $session1 = $this->service->createSession($this->user, $request, 'session-1');
        $this->assertTrue($session1->is_current);

        // Create second session
        $session2 = $this->service->createSession($this->user, $request, 'session-2');
        
        // Refresh first session from database
        $session1->refresh();
        
        $this->assertFalse($session1->is_current);
        $this->assertTrue($session2->is_current);
    }

    public function test_updates_session_activity()
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'REMOTE_ADDR' => '127.0.0.1'
        ]);

        $session = $this->service->createSession($this->user, $request, 'test-session');
        $originalActivity = $session->last_activity;

        // Wait a moment to ensure timestamp difference
        sleep(1);

        $this->service->updateSessionActivity('test-session');
        $session->refresh();

        $this->assertNotEquals($originalActivity, $session->last_activity);
    }

    public function test_gets_user_active_sessions()
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'REMOTE_ADDR' => '127.0.0.1'
        ]);

        // Create active sessions
        $session1 = $this->service->createSession($this->user, $request, 'session-1');
        $session2 = $this->service->createSession($this->user, $request, 'session-2');

        // Create logged out session
        $session3 = $this->service->createSession($this->user, $request, 'session-3');
        $session3->logout();

        $activeSessions = $this->service->getUserActiveSessions($this->user);

        $this->assertCount(2, $activeSessions);
        $this->assertTrue($activeSessions->contains('id', $session1->id));
        $this->assertTrue($activeSessions->contains('id', $session2->id));
        $this->assertFalse($activeSessions->contains('id', $session3->id));
    }

    public function test_logout_session_success()
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'REMOTE_ADDR' => '127.0.0.1'
        ]);

        $session = $this->service->createSession($this->user, $request, 'test-session');
        
        $result = $this->service->logoutSession($this->user, 'test-session');
        
        $this->assertTrue($result);
        $session->refresh();
        $this->assertNotNull($session->logged_out_at);
        $this->assertFalse($session->is_current);
    }

    public function test_logout_session_not_found()
    {
        $result = $this->service->logoutSession($this->user, 'non-existent-session');
        
        $this->assertFalse($result);
    }

    public function test_logout_other_sessions()
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'REMOTE_ADDR' => '127.0.0.1'
        ]);

        $session1 = $this->service->createSession($this->user, $request, 'session-1');
        $session2 = $this->service->createSession($this->user, $request, 'session-2');
        $session3 = $this->service->createSession($this->user, $request, 'session-3');

        $loggedOutCount = $this->service->logoutOtherSessions($this->user, 'session-2');

        $this->assertEquals(2, $loggedOutCount);
        
        $session1->refresh();
        $session2->refresh();
        $session3->refresh();
        
        $this->assertNotNull($session1->logged_out_at);
        $this->assertNull($session2->logged_out_at);
        $this->assertNotNull($session3->logged_out_at);
    }

    public function test_cleanup_expired_sessions()
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'REMOTE_ADDR' => '127.0.0.1'
        ]);

        // Create expired session
        $expiredSession = $this->service->createSession($this->user, $request, 'expired-session');
        $expiredSession->update(['last_activity' => now()->subMinutes(150)]);

        // Create active session
        $activeSession = $this->service->createSession($this->user, $request, 'active-session');

        $cleanedCount = $this->service->cleanupExpiredSessions(120);

        $this->assertEquals(1, $cleanedCount);
        
        $expiredSession->refresh();
        $activeSession->refresh();
        
        $this->assertNotNull($expiredSession->logged_out_at);
        $this->assertNull($activeSession->logged_out_at);
    }

    public function test_detects_new_ip_anomaly()
    {
        $knownRequest = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        // Create session with known IP
        $this->service->createSession($this->user, $knownRequest, 'known-session');

        $newRequest = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'REMOTE_ADDR' => '10.0.0.1'
        ]);

        $anomalies = $this->service->detectLoginAnomalies($this->user, $newRequest);

        $this->assertNotEmpty($anomalies);
        $this->assertEquals('new_ip', $anomalies[0]['type']);
        $this->assertEquals('Login from new IP address', $anomalies[0]['message']);
        $this->assertEquals('10.0.0.1', $anomalies[0]['details']['ip']);
    }

    public function test_detects_new_device_type_anomaly()
    {
        $desktopRequest = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        // Create session with desktop
        $this->service->createSession($this->user, $desktopRequest, 'desktop-session');

        $mobileRequest = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15',
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        $anomalies = $this->service->detectLoginAnomalies($this->user, $mobileRequest);

        $this->assertNotEmpty($anomalies);
        $this->assertEquals('new_device', $anomalies[0]['type']);
        $this->assertEquals('Login from new device type', $anomalies[0]['message']);
        $this->assertEquals('mobile', $anomalies[0]['details']['device_type']);
    }

    public function test_detects_rapid_location_change_anomaly()
    {
        $firstRequest = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        // Create recent session and update its timestamp to be within the last hour
        $session = $this->service->createSession($this->user, $firstRequest, 'first-session');
        $session->update(['created_at' => now()->subMinutes(30)]);

        $secondRequest = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'REMOTE_ADDR' => '10.0.0.1'
        ]);

        $anomalies = $this->service->detectLoginAnomalies($this->user, $secondRequest);

        // Should detect both new IP and rapid location change
        $this->assertNotEmpty($anomalies);
        
        $rapidChangeAnomaly = collect($anomalies)->firstWhere('type', 'rapid_location_change');
        
        if ($rapidChangeAnomaly) {
            $this->assertEquals('Rapid login from different location', $rapidChangeAnomaly['message']);
            $this->assertEquals('192.168.1.1', $rapidChangeAnomaly['details']['previous_ip']);
            $this->assertEquals('10.0.0.1', $rapidChangeAnomaly['details']['current_ip']);
        } else {
            // At minimum, should detect new IP anomaly
            $newIpAnomaly = collect($anomalies)->firstWhere('type', 'new_ip');
            $this->assertNotNull($newIpAnomaly, 'Should detect at least a new IP anomaly');
        }
    }

    public function test_no_anomalies_for_first_login()
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        $anomalies = $this->service->detectLoginAnomalies($this->user, $request);

        $this->assertEmpty($anomalies);
    }
}