
exports.up = function(knex, Promise) {
  return knex.schema.table ('json', function (t) {
    t.string ("access_code");
  });
};

exports.down = function(knex, Promise) {
  return knex.schema.table ('json', function (t) {
    t.dropColumn("access_code");
  });
};
