<?php
/**
 * Route Point-only tables to a separate schema on the SAME MySQL/MariaDB
 * server as kp_db(). One PDO/one InnoDB transaction is required for safe
 * redemption/stock/ledger changes. Remote hosts are intentionally unsupported.
 */
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

function kp_points_schema_name(): string {
    $name=(string)kp_config('points_db.name','');
    $env=getenv('KPAH_POINTS_DB_NAME');
    if($env!==false && $env!=='')$name=(string)$env;
    if($name!=='' && !preg_match('/^[A-Za-z0-9_]{1,64}$/D',$name))
        throw new RuntimeException('Tên database Point không hợp lệ.');
    return $name;
}

/** Qualifies actual table positions, not SQL string literals or game tables. */
function kp_points_sql(string $sql, ?string $schemaOverride=null): string {
    $schema=$schemaOverride===null?kp_points_schema_name():$schemaOverride;
    if($schema==='')return $sql;
    if(!preg_match('/^[A-Za-z0-9_]{1,64}$/D',$schema))
        throw new InvalidArgumentException('Tên database Point không hợp lệ.');
    $tables='(?:wallet|ledger|rewards|redemptions|program|reward_display|redemption_events|redemption_targets|audit|reward_tiers|redemption_control)';
    // SHOW TABLES LIKE normally queries only the default database.
    if(preg_match('/^\s*SHOW\s+TABLES\s+LIKE\s+["\'](kp_points_'.$tables.')["\']\s*$/i',$sql,$m)) {
        return "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='".$schema."' AND TABLE_NAME='".$m[1]."' LIMIT 1";
    }
    $pattern="/'(?:''|[^'])*'|\"(?:\"\"|[^\"])*\"|\\b(FROM|JOIN|UPDATE|INTO|TABLE)\\s+(kp_points_".$tables.")\\b/i";
    return preg_replace_callback($pattern,static function(array $m)use($schema): string {
        if(!isset($m[2]))return $m[0]; // Quoted SQL string, not a table.
        return $m[1].' '.chr(96).$schema.chr(96).'.'.chr(96).$m[2].chr(96);
    },$sql);
}
