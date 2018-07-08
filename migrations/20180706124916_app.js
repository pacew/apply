
exports.up = function(knex, Promise) {
  return knex.schema
    .createTable('applications', function (t) {
      t.integer('app_id');
      t.integer('perf_id');
      t.string('perf_name');
      t.string('email');
    })
  
};

exports.down = function(knex, Promise) {
  return knex.schema
    .dropTableIfExists ("apps")
    .dropTableIfExists ("applications")
};
