<?php
/**
 * Scoped responsive contract for bank top-up transaction history.
 * No account data, payments or database writes.
 */
$root=dirname(__DIR__);
$bank=(string)file_get_contents($root.'/app/topup/bank.php');
$css=(string)file_get_contents($root.'/assets/css/topup-player-light.css');
$global=(string)file_get_contents($root.'/assets/css/topup.css');
$shared=(string)file_get_contents($root.'/assets/css/site-responsive.css');
$layout=(string)file_get_contents($root.'/app/topup/_layout.php');
function bank_history_assert($ok,$name) {
    if (!$ok) throw new RuntimeException('Bank history responsive regression: '.$name);
}
bank_history_assert(strpos($bank,'class="tp-table-wrap tp-bank-history-scroll"')!==false,
    'only the bank history wrapper owns horizontal scrolling');
bank_history_assert(strpos($bank,'class="tp-table tp-bank-history-table"')!==false,
    'six-column history has a dedicated table selector');
bank_history_assert(strpos($bank,'role="region"')!==false
    && strpos($bank,'tabindex="0"')!==false,
    'keyboard users must be able to scroll the history table');
bank_history_assert(strpos($bank,'scope="col"')!==false,
    'table headers must be semantic');
bank_history_assert(strpos($bank,'tp-bank-history-hint')!==false,
    'mobile hint tells users to swipe sideways');
bank_history_assert(strpos($css,'min-width:900px')!==false
    && strpos($css,'tp-bank-history-scroll > table.tp-bank-history-table')!==false,
    'bank history must override shared min-width:0 rule');
bank_history_assert(strpos($css,'white-space:nowrap')!==false
    && strpos($css,'word-break:normal')!==false
    && strpos($css,'overflow-wrap:normal')!==false,
    'history columns must not display vertically broken text');
bank_history_assert(strpos($css,'overflow-x:auto')!==false
    && strpos($css,'-webkit-overflow-scrolling:touch')!==false,
    'mobile horizontal swipe must be enabled');
bank_history_assert(strpos($global,'tp-table--narrow')!==false,
    'other small tables must retain their responsive style');
bank_history_assert(strpos($shared,'.tp-page .tp-table{white-space:normal}')!==false,
    'test must continue to cover known shared CSS that caused the regression');
bank_history_assert(strpos($layout,'topup-player-light.css')!==false,
    'scoped fix must be loaded after shared styles');
bank_history_assert(strpos($bank,'kp_bank_is_wallet_order')!==false
    && strpos($bank,'kp_bank_order_bonus')!==false,
    'finance history values remain calculated by the original backend');
echo "PASS: bank history readable columns, scrolling and finance contract\n";
