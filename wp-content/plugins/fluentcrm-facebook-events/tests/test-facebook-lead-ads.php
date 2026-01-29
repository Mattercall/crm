<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/includes/class-facebook-lead-ads.php';

class FacebookLeadAdsTest extends TestCase
{
    public function test_extracts_leadgen_id_from_webhook_payload()
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'leadgen_id' => 'leadgen_123',
                                'id' => 'fallback_123',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $handler = new FCRM_FB_Events_Lead_Ads();

        $this->assertSame('leadgen_123', $handler->extract_leadgen_id_from_payload($payload));
    }

    public function test_falls_back_to_id_when_leadgen_id_missing()
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'id' => 'fallback_456',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $handler = new FCRM_FB_Events_Lead_Ads();

        $this->assertSame('fallback_456', $handler->extract_leadgen_id_from_payload($payload));
    }
}
