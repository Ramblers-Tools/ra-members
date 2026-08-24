# Supporter API 1.0.0 field mapping

Source: `https://app.swaggerhub.com/apis/JAMESKEARS/ramblers-group-email/1.0.0`

This matrix freezes the Phase 0 mapping decisions. `memberRef` identifies one
input supporter and one `#__ra_profiles` row. Email identifies a Joomla user, so
more than one profile may link to the same `#__users.id`.

| API field | Profile/user target | Rule |
| --- | --- | --- |
| `memberRef` | `#__ra_profiles.memberRef` | Required, immutable, unique profile identity |
| `contactId` | `#__ra_profiles.contactId` | Nullable, non-unique diagnostic metadata |
| `membershipNo` | `#__ra_profiles.membershipNumber` | Nullable |
| `title` | `#__ra_profiles.title` | Store |
| `firstName` | `#__ra_profiles.firstName` | Store; combine with last name for Joomla name |
| `lastName` | `#__ra_profiles.lastName` | Required by the API; combine with first name |
| `email` | `#__users.email` | Never store in profile; null means no user/subscription |
| `friendlyName` | — | Ignore; it must not overwrite locally managed `preferred_name` |
| `landline` | `#__ra_profiles.landlineTelephone` | Store |
| `mobile` | `#__ra_profiles.mobileNumber` | Store |
| `membershipStatus` | `#__ra_profiles.memberStatus` | Store |
| `memberType` | `#__ra_profiles.memberType` | Store |
| `membershipJoinDate` | `#__ra_profiles.ramblersJoinedDate` | Store as date |
| `membershipExpiry` | `#__ra_profiles.membershipExpiryDate` | Store as date |
| `membershipEndDate` | `#__ra_profiles.membershipEndDate` | Store as date |
| `teamStatus` | `#__ra_profiles.teamStatus` | Store for reporting |
| `teamRelationshipFrom` | `#__ra_profiles.groupJoinedDate` | Store as date |
| requested `team_code` | `#__ra_profiles.home_group` and `groupCode` | Derived from `com_ra_mailman.default_group` |
| `wellbeingWalker` | `#__ra_profiles.wellbeingWalker` | Store as `Y`/null |
| `walkLeader` | `#__ra_profiles.walkLeader` | Store as `Y`/null |
| `volunteerRoles` | `#__ra_roles` | Phase 2 mapping; not a profile column |
| `noWalkProgram` | `#__ra_profiles.walkProgrammeOptOut` | Store as `Y`/null |
| `doNotEmail` | — | Ignore for devolved processing |
| `emailConsent` | — | Ignore for devolved processing |
| `canEmailVolunteers` | — | Documentary only; ignore |
| `canEmailMembers` | — | Documentary only; ignore |
| `canEmailWellbeingWalkers` | — | Documentary only; ignore |
| Remaining consent/view flags | — | Ignore unless a later reviewed requirement assigns them |

For a new profile, initialise `preferred_name` from the full display name. Later
automated imports preserve it. A blank mapped field in an RA Members CSV import
clears the corresponding profile field.
