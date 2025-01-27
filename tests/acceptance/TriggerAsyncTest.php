<?php

namespace acceptance;

use PHPUnit\Framework\TestCase;
use Pusher\Pusher;
use stdClass;

class TriggerAsyncTest extends TestCase
{
    /**
     * @var Pusher
     */
    private $pusher;

    protected function setUp(): void
    {
        if (PUSHERAPP_AUTHKEY === '' || PUSHERAPP_SECRET === '' || PUSHERAPP_APPID === '') {
            self::markTestSkipped('Please set the
            PUSHERAPP_AUTHKEY, PUSHERAPP_SECRET and
            PUSHERAPP_APPID keys.');
        } else {
            $this->pusher = new Pusher(PUSHERAPP_AUTHKEY, PUSHERAPP_SECRET, PUSHERAPP_APPID, ['cluster' => PUSHERAPP_CLUSTER]);
        }
    }

    public function testObjectConstruct(): void
    {
        self::assertNotNull($this->pusher, 'Created new \Pusher\Pusher object');
    }

    public function testStringPush(): void
    {
        $result = $this->pusher->triggerAsync('test_channel', 'my_event', 'Test string')->wait();
        self::assertEquals(new stdClass(), $result);
    }

    public function testArrayPush(): void
    {
        $result = $this->pusher->triggerAsync('test_channel', 'my_event', ['test' => 1])->wait();
        self::assertEquals(new stdClass(), $result);
    }

    public function testPushWithSocketId(): void
    {
        $result = $this->pusher->triggerAsync('test_channel', 'my_event', ['test' => 1], ['socket_id' => '123.456'])->wait();
        self::assertEquals(new stdClass(), $result);
    }

    public function testPushWithInfo(): void
    {
        $expectedMyChannel = new stdClass();
        $expectedMyChannel->subscription_count = 1;
        $expectedPresenceMyChannel = new stdClass();
        $expectedPresenceMyChannel->user_count = 0;
        $expectedPresenceMyChannel->subscription_count = 0;
        $expectedResult = new stdClass();
        $expectedResult->channels = [
            "my-channel" => $expectedMyChannel,
            "presence-my-channel" => $expectedPresenceMyChannel,
        ];

        $result = $this->pusher->triggerAsync(['my-channel', 'presence-my-channel'], 'my_event', ['test' => 1], ['info' => 'user_count,subscription_count'])->wait();
        self::assertEquals($expectedResult, $result);
    }

    public function testTLSPush(): void
    {
        $options = [
            'useTLS' => true,
            'cluster' => PUSHERAPP_CLUSTER
        ];
        $pusher = new Pusher(PUSHERAPP_AUTHKEY, PUSHERAPP_SECRET, PUSHERAPP_APPID, $options);

        $result = $pusher->triggerAsync('test_channel', 'my_event', ['encrypted' => 1])->wait();
        self::assertEquals(new stdClass(), $result);
    }

    public function testSendingOver10kBMessageReturns413(): void
    {
        $this->expectException(\Pusher\ApiErrorException::class);
        $this->expectExceptionCode('413');

        $data = str_pad('', 11 * 1024, 'a');
        $this->pusher->triggerAsync('test_channel', 'my_event', $data, [], true)->wait();
    }

    public function testTriggeringEventOnOver100ChannelsThrowsException(): void
    {
        $this->expectException(\Pusher\PusherException::class);

        $channels = [];
        while (count($channels) <= 101) {
            $channels[] = ('channel-' . count($channels));
        }
        $data = ['event_name' => 'event_data'];
        $this->pusher->triggerAsync($channels, 'my_event', $data)->wait();
    }

    public function testTriggeringEventOnMultipleChannels(): void
    {
        $data = ['event_name' => 'event_data'];
        $channels = ['test_channel_1', 'test_channel_2'];
        $result = $this->pusher->triggerAsync($channels, 'my_event', $data)->wait();
        self::assertEquals(new stdClass(), $result);
    }

    public function testTriggeringEventOnPrivateEncryptedChannelSuccess(): void
    {
        $options = ['encryption_master_key_base64' => 'Y0F6UkgzVzlGWk0zaVhxU05JR3RLenR3TnVDejl4TVY=',
                    'cluster' => PUSHERAPP_CLUSTER];
        $this->pusher = new Pusher(PUSHERAPP_AUTHKEY, PUSHERAPP_SECRET, PUSHERAPP_APPID, $options);

        $data = ['event_name' => 'event_data'];
        $channels = ['private-encrypted-ceppaio'];
        $result = $this->pusher->triggerAsync($channels, 'my_event', $data)->wait();
        self::assertEquals(new stdClass(), $result);
    }

    public function testTriggeringEventOnMultipleChannelsWithEncryptedChannelPresentError(): void
    {
        $this->expectException(\Pusher\PusherException::class);

        $options = ['encryption_master_key_base64' => 'Y0F6UkgzVzlGWk0zaVhxU05JR3RLenR3TnVDejl4TVY=',
                    'cluster' => PUSHERAPP_CLUSTER];
        $this->pusher = new Pusher(PUSHERAPP_AUTHKEY, PUSHERAPP_SECRET, PUSHERAPP_APPID, $options);

        $data = ['event_name' => 'event_data'];
        $channels = ['my-chan-ceppaio', 'private-encrypted-ceppaio'];
        $this->pusher->triggerAsync($channels, 'my_event', $data)->wait();
    }

    public function testTriggeringApiExceptionIfConnectionErrorOcurred(): void
    {
        $this->expectException(\Pusher\ApiErrorException::class);

        $options = ['host' => 'invalidhost'];
        $this->pusher = new Pusher(PUSHERAPP_AUTHKEY, PUSHERAPP_SECRET, PUSHERAPP_APPID, $options);

        $this->pusher->triggerAsync('test_channel', 'my_event', 'event_data')->wait();
    }
}
