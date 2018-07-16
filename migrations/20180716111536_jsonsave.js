
exports.up = function(knex, Promise) {
  return knex.schema
    .createTable ('json', function (t) {
      t.string ("app_id"),
      t.timestamp ("ts"),
      t.string ("username"),
      t.string ("val", 100000)
    });
};

exports.down = function(knex, Promise) {
  return knex.schema
    .dropTableIfExists ("json")
};
