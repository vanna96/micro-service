from sqlalchemy.ext.declarative import as_declarative, declared_attr
from sqlalchemy import Column, DateTime
from sqlalchemy.orm import Query
from datetime import datetime

# Custom query class to automatically exclude soft-deleted rows
class SoftDeleteQuery(Query):
    def __new__(cls, *args, **kwargs):
        return super().__new__(cls)

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._with_deleted = False

    def with_deleted(self):
        # Allows including soft-deleted records if needed
        self._with_deleted = True
        return self

    def _apply_filters(self):
        # Apply soft delete filter globally by default
        if not self._with_deleted:
            self = self.filter(self._only_not_deleted())
        return self

    def __iter__(self):
        # Apply the filters before the query executes
        self = self._apply_filters()
        return super().__iter__()

    def _only_not_deleted(self):
        # Return filter to exclude soft-deleted records
        return self.entity_zero.class_.deleted_at == None

@as_declarative()
class Base:
    id: int
    __name__: str

    @declared_attr
    def __tablename__(cls):
        return cls.__name__.lower()

    deleted_at = Column(DateTime, nullable=True)

    def soft_delete(self):
        """Mark the object as deleted by setting `deleted_at`."""
        self.deleted_at = datetime.utcnow()
