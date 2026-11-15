<?php
declare(strict_types=1);
namespace SuperSoft\Plugin\System\SmartTagEnhancements;

use Joomla\CMS\Uri\Uri;

final class ReturnUrl
{
    public static function decode(string $encoded, string $fallback): string
    {
        $url = base64_decode($encoded, true);
        if ($url === false || $url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return $fallback;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $root = new Uri(Uri::root());
            $url = $root->toString(['scheme', 'host', 'port']) . $url;
        }
        $uri = new Uri($url);
        if (!in_array(strtolower((string) $uri->getScheme()), ['', 'http', 'https'], true)
            || !Uri::isInternal($url)
            || str_starts_with((string) $uri->getVar('task', ''), 'tag.')) {
            return $fallback;
        }
        return $url;
    }
}
