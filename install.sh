drush si --yes
drush upwd admin admin
drush user:role:add administrator admin
drush cset system.site uuid 05104925-b5ef-447d-a96a-bb6b3eed6182 --yes
drush entity-delete shortcut_set --yes
drush cim --yes
# For restoring shortcuts.
drush php-eval 'include_once "core/profiles/standard/standard.install"; standard_install();'

drush user:create editor --password=editor
drush user:role:add content_editor editor

drush en dashboard_default_content --yes
