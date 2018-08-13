
exports.up = function(knex, Promise) {
  return knex.schema
    .createTable ('email_history', function (t) {
      t.string ("email"),
      t.datetime ("sent")
    });
};

exports.down = function(knex, Promise) {
  return knex.schema
    .dropTableIfExists ("email_history");
};
