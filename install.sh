drush site-install --yes
drush user:password admin admin
drush user:role:add administrator admin
drush config-set system.site uuid 05104925-b5ef-447d-a96a-bb6b3eed6182 --yes
drush entity-delete shortcut_set --yes
drush config-import --yes
# For restoring shortcuts.
drush php-eval 'include_once "core/profiles/standard/standard.install"; standard_install();'

drush user:create editor --password=editor
drush user:role:add content_editor editor

# This is enabled by config for now.
# drush en dashboard_default_content --yes
