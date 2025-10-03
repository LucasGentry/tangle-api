<?php
it('uses mysql in tests', function () {
    $driver = DB::connection()->getDriverName();
    expect($driver)->toBe('mysql');
});