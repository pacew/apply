
exports.up = function(knex, Promise) {
  return knex.schema
    .createTable('apps', function (t) {
      t.integer('app_id');
      t.string('name');
    })
  
};

exports.down = function(knex, Promise) {
  return knex.schema
    .dropTableIfExists ("apps")
};
