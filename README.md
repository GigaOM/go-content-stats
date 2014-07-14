# go-content-stats

Gigaom Content Stats - stats about posts and authors

## Dependencies

* [go-google](https://github.com/GigaOM/go-google)
* [go-graphing](https://github.com/GigaOM/go-graphing)
* [go-timepicker](https://github.com/GigaOM/go-timepicker)
* [go-ui](https://github.com/GigaOM/go-ui)

## WP CLI

### Load data from Google Analytics

```
wp go_content_stats fetch --start='-3 weeks' --end='now' --json-dir='/tmp/google-analytics-json/' --url=url.of.site.com --path=/path/to/wordpress
```

### Backfill Post IDs of fetched stats

```
wp go_content_stats fill_post_ids --url=url.of.site.com --path=/path/to/wordpress
```
