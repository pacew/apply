# Bundled immutable data

`Webgrid-2026.tsv` is the published grid of the 2026 festival, copied from
`~/aux/webgrid.tsv` on apply.neffa.org on 2026-11-03.

It is **history**, not working data. The program committee uses it only to give
the 2027 slots their initial colour/type balance; they override placement as
needed. The server never writes to it and the new system never regenerates it.
The new `webgrid.tsv` is generated from the stored 2027 grid.

The file has no `Last-Modified` date in the data response on purpose: its file
date is when the checkout happened, not when the festival data was current.
