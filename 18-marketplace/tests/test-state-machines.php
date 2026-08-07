<?php
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/includes/class-mkt-state-machines.php';

assert_true(MKT_State_Machines::can('listing', 'draft', 'review'), 'Draft listing can enter review.');
assert_true(!MKT_State_Machines::can('listing', 'draft', 'active'), 'Draft listing cannot bypass review.');
assert_true(MKT_State_Machines::can('listing', 'removed', 'appealed'), 'Removed listing has appeal path.');
assert_true(MKT_State_Machines::can('offer', 'open', 'accepted'), 'Open offer can be accepted.');
assert_true(!MKT_State_Machines::can('offer', 'accepted', 'open'), 'Accepted offer cannot reopen.');
assert_true(MKT_State_Machines::can('deal', 'arranging', 'disputed'), 'Arranging deal can be disputed.');
assert_true(MKT_State_Machines::can('deal', 'disputed', 'resolved'), 'Disputed deal can be resolved.');
assert_true(!MKT_State_Machines::can('deal', 'closed', 'accepted'), 'Closed deal is terminal.');
assert_true(MKT_State_Machines::can('report', 'decided', 'appealed'), 'Moderation decision can be appealed.');
assert_true(MKT_State_Machines::can('dispute', 'decided', 'appealed'), 'Dispute decision can be appealed.');
$error = MKT_State_Machines::assert('listing', 'active', 'draft');
assert_true(is_wp_error($error) && $error->get_error_code() === 'mkt_invalid_transition', 'Invalid transition returns stable error.');
assert_same(['review'], MKT_State_Machines::allowed('listing', 'draft'), 'Allowed transitions are deterministic.');
fwrite(STDOUT, "State machine suite complete.\n");
