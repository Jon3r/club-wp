#!/usr/bin/env python3
"""Mirror of Clubworx_Event_Filters logic for offline verification."""

from __future__ import annotations

AVAILABLE_KEYS = [
    "available_prospect_spaces",
    "prospect_spaces_available",
    "remaining_prospect_spaces",
    "prospects_available",
    "available_prospects",
    "free_prospect_spaces",
    "prospect_spaces_remaining",
]
MAX_KEYS = [
    "max_prospects",
    "event_max_prospects",
    "prospect_spaces",
    "prospect_space",
    "max_prospect_spaces",
    "prospects_max",
]
BOOKED_KEYS = [
    "prospects_booked",
    "booked_prospects",
    "prospect_bookings",
    "prospects_count",
    "prospect_attendees",
]


def class_name_is_excluded(class_name: str, patterns: list[str]) -> bool:
    name = (class_name or "").strip().lower()
    if not name or not patterns:
        return False
    for pattern in patterns:
        needle = (pattern or "").strip().lower()
        if needle and needle in name:
            return True
    return False


def event_has_prospect_capacity_fields(event: dict) -> bool:
    for key in AVAILABLE_KEYS + MAX_KEYS + BOOKED_KEYS:
        if key in event:
            return True
    if isinstance(event.get("prospects"), dict):
        return True
    for flag in ("allows_prospects", "allow_prospects", "free_trial", "is_free_trial"):
        if flag in event:
            return True
    return False


def event_has_free_trial_space(event: dict) -> bool:
    for key in AVAILABLE_KEYS:
        if key in event and isinstance(event[key], (int, float, str)) and str(event[key]).lstrip("-").isdigit():
            return int(event[key]) > 0

    prospects = event.get("prospects")
    if isinstance(prospects, dict):
        if "available" in prospects:
            return int(prospects["available"]) > 0
        if "remaining" in prospects:
            return int(prospects["remaining"]) > 0
        if "max" in prospects:
            booked = int(prospects.get("booked", 0) or 0)
            return int(prospects["max"]) - booked > 0

    max_val = None
    for key in MAX_KEYS:
        if key in event and str(event[key]).lstrip("-").isdigit():
            max_val = int(event[key])
            break
    if max_val is not None:
        booked = 0
        for key in BOOKED_KEYS:
            if key in event and str(event[key]).lstrip("-").isdigit():
                booked = int(event[key])
                break
        return max_val - booked > 0

    for flag in ("allows_prospects", "allow_prospects", "free_trial", "is_free_trial"):
        if flag in event:
            return bool(event[flag])
    return False


def event_allowed_for_booking(event, exclude_patterns, free_trial_only, prospect_fields_present):
    name = (
        event.get("event_name")
        or event.get("name")
        or event.get("class_name")
        or event.get("title")
        or ""
    )
    if class_name_is_excluded(name, exclude_patterns):
        return False
    if free_trial_only and prospect_fields_present and not event_has_free_trial_space(event):
        return False
    return True


def main() -> None:
    assert class_name_is_excluded("Adults Private Training", ["Private Training"])
    assert class_name_is_excluded("private training - 1hr", ["Private Training"])
    assert not class_name_is_excluded("Adults Foundations", ["Private Training"])

    trial = {"event_name": "Kids Warriors", "max_prospects": 2, "prospects_booked": 1}
    full = {"event_name": "Kids Warriors", "max_prospects": 1, "prospects_booked": 1}
    private = {"event_name": "Private Training", "max_prospects": 5, "prospects_booked": 0}
    no_fields = {"event_name": "Adults General"}

    events = [trial, full, private, no_fields]
    present = any(event_has_prospect_capacity_fields(e) for e in events)
    assert present is True

    assert event_allowed_for_booking(trial, ["Private Training"], True, present) is True
    assert event_allowed_for_booking(full, ["Private Training"], True, present) is False
    assert event_allowed_for_booking(private, ["Private Training"], True, present) is False
    # When API exposes prospect fields, classes without them are not trial-bookable.
    assert event_allowed_for_booking(no_fields, ["Private Training"], True, present) is False

    # When no prospect fields exist anywhere, free-trial filter is skipped; exclusions still apply.
    bare = [{"event_name": "Adults General"}, {"event_name": "Private Training"}]
    bare_present = any(event_has_prospect_capacity_fields(e) for e in bare)
    assert bare_present is False
    assert event_allowed_for_booking(bare[0], ["Private Training"], True, bare_present) is True
    assert event_allowed_for_booking(bare[1], ["Private Training"], True, bare_present) is False

    # Nested available spaces
    nested = {"event_name": "Women", "prospects": {"available": 2}}
    assert event_has_free_trial_space(nested) is True

    print("OK: event filter tests passed")


if __name__ == "__main__":
    main()
