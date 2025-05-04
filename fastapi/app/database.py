from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from dotenv import load_dotenv
from models.base import SoftDeleteQuery  # Import custom Base and SoftDeleteQuery

import os

# Load environment variables from .env file
load_dotenv()

# Ensure that the DATABASE_URL is loaded from the environment
DATABASE_URL = os.getenv("DATABASE_URL")
if DATABASE_URL is None:
    raise ValueError("DATABASE_URL environment variable is not set.")

# Create the SQLAlchemy engine and session
engine = create_engine(DATABASE_URL, echo=True)
SessionLocal = sessionmaker(
    bind=engine,
    autocommit=False,
    autoflush=False,
    query_cls=SoftDeleteQuery  # Use the custom query class
)

# Optionally, you can add a function to get the database session
def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
