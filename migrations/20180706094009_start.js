
exports.up = function(knex, Promise) {
  return knex.schema
    .createTable('vars', function (t) {
      t.string('var');
      t.string('val');
    })
    .createTable ('sessions', function (t) {
      t.text('session_id');
      t.datetime('updated');
      t.text('session', 'longtext');
    })
    .createTable ('seq', function (t) {
      t.integer ('lastval');
    });
};

exports.down = function(knex, Promise) {
  return knex.schema
    .dropTableIfExists ("vars")
    .dropTableIfExists ("sessions")
    .dropTableIfExists ("seq");
};
