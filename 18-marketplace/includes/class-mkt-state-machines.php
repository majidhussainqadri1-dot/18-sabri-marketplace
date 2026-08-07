<?php
defined('ABSPATH') || exit;

final class MKT_State_Machines {
    private const MAP = [
        'listing' => [
            'draft' => ['review'],
            'review' => ['active','rejected','draft'],
            'active' => ['paused','sold_unavailable','expired','removed'],
            'paused' => ['active','expired','removed'],
            'sold_unavailable' => ['active','closed'],
            'expired' => ['review','removed'],
            'rejected' => ['draft','appealed'],
            'removed' => ['appealed'],
            'appealed' => ['review','rejected','removed','active'],
        ],
        'offer' => [
            'open' => ['countered','accepted','declined','withdrawn','expired'],
            'countered' => ['countered','accepted','declined','withdrawn','expired'],
            'accepted' => [],
            'declined' => [],
            'withdrawn' => [],
            'expired' => [],
        ],
        'deal' => [
            'accepted' => ['arranging','cancelled','disputed'],
            'arranging' => ['completed','cancelled','disputed'],
            'completed' => ['disputed','closed'],
            'cancelled' => ['disputed','closed'],
            'disputed' => ['resolved','cancelled','completed'],
            'resolved' => ['closed'],
            'closed' => [],
        ],
        'report' => [
            'submitted' => ['triaged'],
            'triaged' => ['restricted','no_action','decided'],
            'restricted' => ['decided','appealed'],
            'no_action' => ['appealed','closed'],
            'decided' => ['appealed','closed'],
            'appealed' => ['triaged','decided','closed'],
            'closed' => [],
        ],
        'dispute' => [
            'opened' => ['evidence','review','withdrawn'],
            'evidence' => ['review','withdrawn'],
            'review' => ['decided','more_info'],
            'more_info' => ['evidence','review','closed'],
            'decided' => ['appealed','closed'],
            'appealed' => ['review','decided','closed'],
            'withdrawn' => ['closed'],
            'closed' => [],
        ],
    ];

    public static function can(string $machine, string $from, string $to): bool {
        $machine = sanitize_key($machine);
        $from = sanitize_key($from);
        $to = sanitize_key($to);
        return isset(self::MAP[$machine][$from]) && in_array($to, self::MAP[$machine][$from], true);
    }

    public static function allowed(string $machine, string $from): array {
        return self::MAP[sanitize_key($machine)][sanitize_key($from)] ?? [];
    }

    public static function assert(string $machine, string $from, string $to): bool|WP_Error {
        if (self::can($machine, $from, $to)) {
            return true;
        }
        return new WP_Error(
            'mkt_invalid_transition',
            sprintf(__('The %1$s transition from %2$s to %3$s is not allowed.', 'marketplace'), $machine, $from, $to),
            ['status' => 409, 'machine' => $machine, 'from' => $from, 'to' => $to]
        );
    }

    public static function all(): array {
        return self::MAP;
    }
}
