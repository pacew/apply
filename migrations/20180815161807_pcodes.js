
exports.up = function(knex, Promise) {
  return knex.schema
    .createTable ('pcodes', function (t) {
      t.integer("id"),
      t.string("pcode")
    });
};

exports.down = function(knex, Promise) {
  return knex.schema
    .dropTableIfExists ("pcodes");
};
