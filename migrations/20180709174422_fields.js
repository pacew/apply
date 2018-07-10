const fields = [
  "name",
  "phone",
  "individual_or_group",
  "group_name",
  "event_title",
  "event_desc",
  "caller_band_other",
  "band_type",
  "need_caller",
  "preferred_caller",
  "music_pref",
  "preferred_band",
  "recorded_type",
  "device_required",
  "sound",
  "piano",
  "shared",
  "preference",
  "event_type",
  "level",
  "url",
  "notes"
];


exports.up = function(knex, Promise) {
  return knex.schema
    .table('applications', table => {
      for (let name of fields) {
	table.string(name);
      }
    })
    .createTable('overrides', table => {
      table.integer("app_id");
      table.integer("perf_id");
      table.string("perf_name");
      table.string("email");
      for (let name of fields) {
	table.string(name);
      }
    });
};

exports.down = function(knex, Promise) {
  return knex.schema
    .table("applications", table => {
      table.dropColumns (fields);
    })
    .dropTableIfExists ("overrides");
};
