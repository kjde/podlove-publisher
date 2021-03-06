<?php 
namespace Podlove\Modules\Contributors;

use \Podlove\Modules\Contributors\Model\Contributor;
use \Podlove\Modules\Contributors\Model\ContributorRole;
use \Podlove\Modules\Contributors\Model\ContributorGroup;
use \Podlove\Modules\Contributors\Model\EpisodeContribution;
use \Podlove\Modules\Contributors\Model\ShowContribution;
use \Podlove\Modules\Contributors\Model\DefaultContribution;

class TemplateExtensions {
	
	/**
	 * List of episode contributors
	 *
	 * Examples:
	 *
	 * ```
	 * {# iterating over a list of contributors #}
	 * {% for contributor in episode.contributors %}
	 * 	{{ contributor.name }}
	 * 	{% if not loop.last %}, {% endif %}
	 * {% endfor %}
	 * ```
	 * ```
	 * {# iterating over a grouped list of contributors #}
	 * {% for contributorGroup in episode.contributors({groupby: "group"}) %}
	 * 	<strong>{{ contributorGroup.group.title }}:</strong> 
	 * 	{% for contributor in contributorGroup.contributors %}
	 * 		{{ contributor.name }}
	 * 		{% if not loop.last %}, {% endif %}
	 * 	{% endfor %}
	 * {% endfor %}
	 * ```
	 * 
	 * Options:
	 *
	 * - **id:**      Fetch one contributor by its id. Ignores all other parameters. 
	 *                Returns null if the id belongs to an existing contributor which is not part of the episode. 
	 *                Example: `episode.contributors({id: 'james'}).name`
	 * - **group:**   group slug. If none is given, show all contributors.
	 * - **role:**    role slug. If none is given, show all contributors.
	 * - **groupby:** group or role slug. Group by "group" or "role".
	 * 	         If used, the returned data is has another layer for the groups.
	 * 	         See examples for more details.
	 *
	 * @accessor
	 * @dynamicAccessor episode.contributors
	 */
	public function accessorEpisodeContributors($return, $method_name, $episode, $post, $args = array()) {
		$contributions = EpisodeContribution::find_all_by_episode_id($episode->id);
		return \Podlove\Modules\Contributors\Contributors::filterContributions($contributions, $args);
	}

	/**
	 * List of podcast contributors.
	 *
	 * Examples:
	 *
	 * ```
	 * {# iterating over a list of contributors #}
	 * {% for contributor in podcast.contributors({scope: "podcast"}) %}
	 * 	{{ contributor.name }}
	 * 	{% if not loop.last %}, {% endif %}
	 * {% endfor %}
	 * ```
	 * ```
	 * {# iterating over a grouped list of contributors #}
	 * {% for contributorGroup in podcast.contributors({scope: "podcast", groupby: "group"}) %}
	 * 	<strong>{{ contributorGroup.group.title }}:</strong> 
	 * 	{% for contributor in contributorGroup.contributors %}
	 * 		{{ contributor.name }}
	 * 		{% if not loop.last %}, {% endif %}
	 * 	{% endfor %}
	 * {% endfor %}
	 * ```
	 * 
	 * Options:
	 *
	 * - **id:**      Fetch one contributor by its id. Ignores all other parameters.
	 *                Example: `podcast.contributors({id: 'james'}).name`
	 * - **scope:**   Either "global" or "podcast". "global" returns *all* contributors.
	 * 	              "podcast" returns the contributors configured in podcast settings.
	 * 	              Default: "global".
	 * - **group:**   filter by group slug. Defaults to "all", which does not filter.
	 * - **role:**    filter by role slug. Defaults to "all", which does not filter.
	 * - **groupby:** group or role slug. Group by "group" or "role".
	 * 	              If used, the returned data is has another layer for the groups.
	 * 	              See examples for more details.
	 *
	 * @accessor
	 * @dynamicAccessor podcast.contributors
	 */
	public function accessorPodcastContributors($return, $method_name, $podcast, $args = array()) {
		
		if (isset($args['id']))
			return new Template\Contributor(Contributor::find_one_by_slug($args['id']));

		$scope = isset($args['scope']) && in_array($args['scope'], array('global', 'podcast')) ? $args['scope'] : 'global';

		if ($scope == 'global') {
			// fetch by group and/or role. defaults to *all* contributors
			// if no role or group are given
			$group = isset($args['group']) && $args['group'] !== 'all' ? $args['group'] : null;
			$role  = isset($args['role'])  && $args['role']  !== 'all' ? $args['role']  : null;
			$contributors = Contributor::byGroupAndRole($group, $role);

			return array_map(function($contributor) {
				return new Template\Contributor($contributor);
			}, $contributors);
		} else {
			$contributions = ShowContribution::all();
			return \Podlove\Modules\Contributors\Contributors::filterContributions($contributions, $args);
		}
	}
}