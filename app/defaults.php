<?php
/**
 * Default settings & menu values used on fresh install.
 */

return [
    'settings' => [
        'site_name'              => 'Samachar Live',
        'site_tagline'           => 'Your Trusted News Partner',
        'site_lang'              => 'en',
        'site_logo'              => '',
        'site_favicon'           => '',
        'site_email'             => 'contact@samacharlive.local',
        'site_phone'             => '+91 90000 00000',
        'site_address'           => 'Bhubaneswar, Odisha, India',
        'site_footer_text'       => '&copy; ' . date('Y') . ' Samachar Live. All rights reserved.',
        'seo_meta_description'   => 'Latest Odisha news, breaking news, local news, and more.',
        'theme_primary'          => '#c62828',
        'theme_secondary'        => '#1a1a2e',
        'theme_accent'           => '#f9a825',
        'header_style'           => 'center',
        'header_breaking'        => '1',
        'news_per_page'          => '12',
        'facebook'               => '',
        'twitter'                => '',
        'instagram'              => '',
        'youtube'                => '',
        'google_analytics'       => '',
        'maintenance_mode'       => '0',
    ],
    'menus' => [
        ['label' => 'Home', 'url' => '/', 'parent_id' => 0, 'sort_order' => 1],
        ['label' => 'Epaper', 'url' => '/epaper', 'parent_id' => 0, 'sort_order' => 99],
        ['label' => 'Contact', 'url' => '/page/contact', 'parent_id' => 0, 'sort_order' => 100],
    ],
];
