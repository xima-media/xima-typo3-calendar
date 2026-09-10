# XML sitemap

The set ships a sitemap definition for the event detail pages, so `EXT:seo` lists them without
any project configuration.

## What is listed

The records are the **appointments**, not the events: the route enhancer addresses appointments,
and list views link to them, so the sitemap emits exactly the URLs a visitor can reach. A series
with 40 dates contributes 40 URLs.

Both the `event` and the `appointment` argument are mapped, so the emitted URL is
`/detail/63-event-slug/a101` — the same URL a list view links to, rather than the shorter
`/detail/a101` variant of the same page.

Every appointment is listed, including past ones: those pages stay valid and indexable.

## Configuration

| Setting | Purpose |
|---------|---------|
| `xima_typo3_calendar.eventShowPid` (site setting) | The detail page the URLs point at |
| `plugin.tx_ximatypo3calendar.persistence.storagePid` (TypoScript constant) | The folder the appointments are read from |

Visibility needs no configuration: `EntryRestriction` is registered as an enforced query
restriction, so an anonymous sitemap request sees live events only. Do **not** add a
`publish_to_website = 1` guard — on installations that do not maintain the flag it silently
empties the sitemap.

## Opting out

Remove the definition in your own TypoScript setup:

```typoscript
plugin.tx_seo.config.xmlSitemap.sitemaps.ximaTypo3CalendarEvents >
```

## Limiting the entries

To exclude dates in the past, narrow the query:

```typoscript
plugin.tx_seo.config.xmlSitemap.sitemaps.ximaTypo3CalendarEvents.config.additionalWhere (
  AND start_date >= UNIX_TIMESTAMP()
)
```
