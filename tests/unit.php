<?php
$path = __DIR__ . '/../includes/domain.php';
if (!is_file($path)) {
    fwrite(STDERR, "FAIL: pricing and workflow helpers are missing\n");
    exit(1);
}
require $path;
function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS $message\n";
}
check(moneyCents('25000.00') === 2500000, 'decimal to integer cents');
check(
    orderTotals(
        [['price' => '25000.00', 'quantity' => 2], ['price' => '20000.00', 'quantity' => 1]],
        'customer'
    ) === ['subtotal' => 7000000, 'discount' => 700000, 'total' => 6300000],
    'customer 10 percent'
);
check(
    orderTotals([['price' => '70000.00', 'quantity' => 1]], null)['total'] === 7000000,
    'guest normal price'
);
check(
    orderTotals([['price' => '10005.00', 'quantity' => 1]], 'customer')['discount'] === 100100,
    'discount rounds half up to rupiah'
);
foreach (['0', '-1', '1.5', '100', [], 'abc'] as $q) {
    try {
        quantity($q);
        check(false, 'invalid quantity rejected');
    } catch (InvalidArgumentException $e) {
    }
}
check(quantity('99') === 99, 'quantity upper bound');
check(canTransition('cashier', 'pending', 'processing'), 'cashier forward');
check(!canTransition('cashier', 'pending', 'completed'), 'cannot skip workflow');
check(!canTransition('cashier', 'ready', 'cancelled'), 'cashier cannot cancel');
check(canTransition('owner', 'ready', 'cancelled'), 'owner may cancel open order');
check(!canTransition('owner', 'completed', 'cancelled'), 'completed immutable');
check(safeSpreadsheetText('=SUM(A1)') === "'=SUM(A1)", 'CSV formula injection');
try {
    validateOrderStorage(orderTotals([['price' => '9999999999.99', 'quantity' => 99]], null));
    check(false, 'oversized order rejected');
} catch (InvalidArgumentException $e) {
    check(true, 'oversized order rejected');
}
check(
    orderTotals([['price' => '70000.00', 'quantity' => 1]], 'owner')['discount'] === 0,
    'only customer role gets discount'
);
echo "Unit checks complete.\n";
