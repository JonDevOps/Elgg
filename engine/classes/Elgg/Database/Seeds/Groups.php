<?php

namespace Elgg\Database\Seeds;

/**
 * Seed users
 *
 * @access private
 */
class Groups extends Seed {

	/**
	 * @var \ElggUser[]
	 */
	private $users;

	private $visibility = [
		ACCESS_PUBLIC,
		ACCESS_LOGGED_IN,
		ACCESS_PRIVATE,
	];

	private $content_access_modes = [
		\ElggGroup::CONTENT_ACCESS_MODE_MEMBERS_ONLY,
		\ElggGroup::CONTENT_ACCESS_MODE_UNRESTRICTED,
	];

	private $membership = [
		ACCESS_PUBLIC,
		ACCESS_PRIVATE,
	];

	/**
	 * {@inheritdoc}
	 */
	public function seed() {

		$count_groups = function () {
			return elgg_get_entities_from_metadata([
				'types' => 'group',
				'metadata_names' => '__faker',
				'count' => true,
			]);
		};

		$count_members = function ($group) {
			return elgg_get_entities_from_relationship([
				'types' => 'user',
				'relationship' => 'member',
				'relationship_guid' => $group->getGUID(),
				'inverse_relationship' => true,
				'metadata_names' => '__faker',
				'count' => true,
			]);
		};

		$exclude = [];

		while ($count_groups() < $this->limit) {
			$group = $this->getRandomGroup($exclude);
			if (!$group) {
				$group = $this->createGroup([
					'access_id' => $this->getRandomVisibility(),
				], [
					'content_access_mode' => $this->getRandomContentAccessMode(),
					'membership' => $this->getRandomMembership(),
				]);
				if (!$group) {
					continue;
				}
			}

			$exclude[] = $group->guid;

			if ($count_members($group) > 1) {
				// exclude owner from count
				continue;
			}

			$members_limit = $this->faker->numberBetween(5, 10);

			$members_exclude = [];

			while ($count_members($group) - 1 < $members_limit) {
				$member = $this->getRandomUser($members_exclude);
				if (!$member) {
					$member = $this->createUser();
					if (!$member) {
						continue;
					}
				}

				$members_exclude[] = $member->guid;

				if ($group->join($member)) {
					$this->log("User $member->name [guid: $member->guid] joined group $group->name [guid: $group->guid]");
				}

				if (!$group->isPublicMembership()) {
					$invitee = $this->getRandomUser($members_exclude);
					if ($invitee) {
						$members_exclude[] = $invitee->guid;
						if (!check_entity_relationship($invitee->guid, 'member', $group->guid)) {
							add_entity_relationship($group->guid, 'invited', $invitee->guid);
							$this->log("User $invitee->name [guid: $invitee->guid] was invited to $group->name [guid: $group->guid]");
						}
					}

					$requestor = $this->getRandomUser($members_exclude);
					if ($requestor) {
						$members_exclude[] = $requestor->guid;
						if (!check_entity_relationship($group->guid, 'invited', $requestor->guid)
							&& !check_entity_relationship($requestor->guid, 'member', $group->guid)
						) {
							add_entity_relationship($requestor->guid, 'membership_request', $group->guid);
							$this->log("User $invitee->name [guid: $invitee->guid] requested to join $group->name [guid: $group->guid]");
						}
					}
				}
			}
		}

	}

	/**
	 * {@inheritdoc}
	 */
	public function unseed() {

		$groups = elgg_get_entities_from_metadata([
			'types' => 'group',
			'metadata_names' => '__faker',
			'limit' => 0,
			'batch' => true,
		]);

		/* @var $groups \ElggBatch */

		$groups->setIncrementOffset(false);

		foreach ($groups as $group) {
			if ($group->delete()) {
				$this->log("Deleted group $group->guid");
			} else {
				$this->log("Failed to delete group $group->guid");
			}
		}
	}

	/**
	 * Returns random visibility value
	 * @return int
	 */
	public function getRandomVisibility() {
		$key = array_rand($this->visibility, 1);

		return $this->visibility[$key];
	}

	/**
	 * Returns random content access mode value
	 * @return string
	 */
	public function getRandomContentAccessMode() {
		$key = array_rand($this->content_access_modes, 1);

		return $this->content_access_modes[$key];
	}

	/**
	 * Returns random membership mode
	 * @return mixed
	 */
	public function getRandomMembership() {
		$key = array_rand($this->membership, 1);

		return $this->membership[$key];
	}
}
