
exports.up = function(knex, Promise) {
  return knex.schema.table ('json', function (t) {
    t.integer ("attention")
  });
};

exports.down = function(knex, Promise) {
  return knex.schema.table ('json', function (t) {
    t.dropColumn ("attention")
  });
};
