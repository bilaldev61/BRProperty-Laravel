<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach ($staticRoutes as $route)
    <url>
        <loc>{{ $route['loc'] }}</loc>
        <lastmod>{{ $lastUpdated->toAtomString() }}</lastmod>
        <changefreq>{{ $route['freq'] }}</changefreq>
        <priority>{{ $route['priority'] }}</priority>
    </url>
@endforeach
@foreach ($properties as $property)
    <url>
        <loc>{{ route('properties.show', $property->slug) }}</loc>
        <lastmod>{{ $property->updated_at->toAtomString() }}</lastmod>
        <changefreq>{{ $property->status === 'sold' ? 'monthly' : 'weekly' }}</changefreq>
        <priority>{{ $property->status === 'sold' ? '0.6' : '0.8' }}</priority>
@foreach ($property->images->take(10) as $image)
        <image:image>
            <image:loc>{{ $image->url() }}</image:loc>
            <image:title>{{ $image->alt ?: $property->title }}</image:title>
        </image:image>
@endforeach
    </url>
@endforeach
</urlset>
