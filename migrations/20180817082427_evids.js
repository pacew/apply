
exports.up = function(knex, Promise) {
  return knex.schema
    .createTable ('evid_info', function (t) {
      t.string("key"),
      t.integer("evid_core")
    });
};

exports.down = function(knex, Promise) {
  return knex.schema
    .dropTableIfExists ("evid_info");
};
