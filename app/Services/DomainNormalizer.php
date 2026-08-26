<?php
namespace App\Services;
class DomainNormalizer {
 public function normalize(string $value, bool $acceptUrl = true): ?string {
  $value=trim($value); if ($value==='') return null;
  if ($acceptUrl && preg_match('~^[a-z][a-z0-9+.-]*://~i',$value)) $value=(string)parse_url($value,PHP_URL_HOST);
  elseif ($acceptUrl) $value=preg_split('~[/\\s?#]~',$value,2)[0]??'';
  $value=strtolower(rtrim(trim($value," \t\n\r\0\x0B\"'[](){}<>"),'.'));
  if ($value==='' || $value==='localhost' || filter_var($value,FILTER_VALIDATE_IP)) return null;
  if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/',$value)) { $ascii=idn_to_ascii($value,IDNA_DEFAULT,INTL_IDNA_VARIANT_UTS46); if ($ascii===false) return null; $value=strtolower($ascii); }
  if (strlen($value)>253 || !preg_match('/^(?=.{1,253}$)(?!-)[a-z0-9-]{1,63}(?<!-)(?:\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/',$value)) return null;
  return $value;
 }
}
