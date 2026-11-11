# One API response format (LV-U03, 11 Nov 2026)

The API grew four answer shapes: `{"Code":"300","msg":...}` (old mobile API), `{"success":...,"data":...}`,
`{"status":...}` and `{"code":...}`. The app in the stores reads them, so they are unchanged by default.

Send the header `X-Xeasy-Response: v2` (or add `?response=v2`) and every JSON answer of `/api/...` (mobile API,
advance API and the other API routes) comes in one envelope:

```json
{
  "ok": true,
  "code": 200,
  "message": "Data updated successfully",
  "data": { "...": "what the endpoint returned, without the status fields" },
  "errors": null,
  "legacy_code": "300"
}
```

- `ok`: true only when the HTTP status is below 400 and the endpoint's own marker says success
  (`Code` 300/200, `success: true`, `status: "success"`).
- `code`: the HTTP status for errors; 200 for success, 400 for a refused request answered with HTTP 200.
- `message`: the endpoint's `message` / `msg`.
- `data`: the endpoint's `data` when that is all it returned, otherwise all its other fields.
- `errors`: validation errors (field => messages) when there are any.
- `legacy_code`: the old `Code`, for comparison while moving over.

Plan: the next app release sends the header on every call and reads only this envelope. Once that release is the
only one in use, the default can switch to v2 and the old shapes can go.

Dates: "no date" is null in v2. Without the header, the mobile API still answers "0000-00-00" in the old-style date
keys (BillDate, Fin_PayDate, ...) for the app in the stores (`LEGACY_ZERO_DATES`, D-09).
