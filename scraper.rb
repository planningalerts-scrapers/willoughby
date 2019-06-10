require "icon_scraper"

IconScraper.scrape_with_params(
  url: "https://eplanning.willoughby.nsw.gov.au/pages/xc.track",
  period: "last90days",
  types: [
    "da01", "da01a", "da02a", "da03", "da05", "da06", "da07",
    "da10", "s96", "cc01a", "cc01b", "cc03", "cc04", "cd01a",
    "cd01b", "cd02", "cd04", "bcertu", "bcertr", "bcertc",
    "tvpa", "tvpa 2", "tvpa r"
  ]
) do |record|
  IconScraper.save(record)
end
