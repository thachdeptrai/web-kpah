<?php
/**
 * Separate forum storage on kpah_points (same MySQL/MariaDB server as kp_db()).
 * No fallback to game DB when this schema is absent.
 */
if(!defined('KP_ROOT')){http_response_code(403);exit;}
function kp_forum_schema_name(): string {
    // Reuse the Point schema, including KPAH_POINTS_DB_NAME override.
    $schema=kp_points_schema_name();
    if($schema==='')$schema='kpah_points';
    if(!preg_match('/^[A-Za-z0-9_]{1,64}$/D',$schema))
        throw new RuntimeException('Cấu hình database Forum không hợp lệ.');
    return $schema;
}
function kp_forum_sql(string $sql,?string $schemaOverride=null): string {
    $schema=$schemaOverride===null?kp_forum_schema_name():$schemaOverride;
    if(!preg_match('/^[A-Za-z0-9_]{1,64}$/D',$schema))
        throw new InvalidArgumentException('Tên database Forum không hợp lệ.');
    $pattern='/\b(FROM|JOIN|UPDATE|INTO|TABLE|REFERENCES)\s+(IF\s+NOT\s+EXISTS\s+)?(kp_forum_(?:topics|replies|actions))\b/i';
    return preg_replace_callback($pattern,static function(array $m)use($schema): string {
        return $m[1].' '.($m[2]??'').chr(96).$schema.chr(96).'.'.chr(96).$m[3].chr(96);
    },$sql);
}
